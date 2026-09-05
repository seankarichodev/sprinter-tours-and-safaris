<?php
require_once __DIR__ . "/admin_auth.php";
requireOwner();
require_once __DIR__ . "/db.php";

function oe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
function flash_set(string $type, string $message): void {
    $_SESSION["owner_booking_flash"] = ["type" => $type, "message" => $message];
}
function audit_booking(mysqli $conn, int $bookingId, string $action, string $details): void {
    global $adminId, $adminUsername, $adminRole;
    $exists = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
    if (!$exists || $exists->num_rows !== 1) return;
    $entityType = "booking";
    $ip = $_SERVER["REMOTE_ADDR"] ?? null;
    $stmt = $conn->prepare("INSERT INTO admin_audit_log (admin_id,username,role,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?,?,?)");
    if (!$stmt) return;
    $stmt->bind_param("issssiss", $adminId, $adminUsername, $adminRole, $action, $entityType, $bookingId, $details, $ip);
    if (!$stmt->execute()) error_log("Owner booking audit log failed for #".$bookingId);
    $stmt->close();
}

$id = isset($_GET["id"]) ? (int)$_GET["id"] : (int)($_POST["booking_id"] ?? 0);
if ($id <= 0) { header("Location: owner_bookings.php"); exit(); }

/* Owner-only internal notes table. Created once automatically. */
$conn->query("CREATE TABLE IF NOT EXISTS booking_owner_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    admin_id INT NOT NULL,
    username VARCHAR(120) NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_owner_notes_booking (booking_id),
    INDEX idx_booking_owner_notes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if (empty($_SESSION["owner_booking_csrf"])) {
    $_SESSION["owner_booking_csrf"] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION["owner_booking_csrf"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = (string)($_POST["csrf_token"] ?? "");
    if (!hash_equals($csrf, $token)) {
        flash_set("error", "Security token expired. Please try again.");
        header("Location: owner_booking_view.php?id=".$id); exit();
    }
    $action = (string)($_POST["action"] ?? "");

    if ($action === "add_note") {
        $note = trim((string)($_POST["note"] ?? ""));
        if ($note === "") {
            flash_set("error", "Write a note before saving.");
        } elseif (mb_strlen($note) > 1500) {
            flash_set("error", "Internal notes must be 1,500 characters or less.");
        } else {
            $stmt = $conn->prepare("INSERT INTO booking_owner_notes (booking_id,admin_id,username,note) VALUES (?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("iiss", $id, $adminId, $adminUsername, $note);
                if ($stmt->execute()) {
                    audit_booking($conn, $id, "Added booking note", "Owner added an internal booking note.");
                    flash_set("success", "Internal note saved.");
                } else {
                    flash_set("error", "Unable to save the note.");
                }
                $stmt->close();
            } else {
                flash_set("error", "Unable to prepare the note.");
            }
        }
        header("Location: owner_booking_view.php?id=".$id); exit();
    }

    if ($action === "cancel_booking") {
        $reason = trim((string)($_POST["reason"] ?? ""));
        if (mb_strlen($reason) < 3) {
            flash_set("error", "Enter a short cancellation reason.");
            header("Location: owner_booking_view.php?id=".$id); exit();
        }
        if (mb_strlen($reason) > 500) {
            flash_set("error", "Cancellation reason must be 500 characters or less.");
            header("Location: owner_booking_view.php?id=".$id); exit();
        }
        $check = $conn->prepare("SELECT payment_status FROM bookings WHERE id=? LIMIT 1");
        $check->bind_param("i", $id); $check->execute();
        $current = $check->get_result()->fetch_assoc(); $check->close();
        if (!$current) {
            flash_set("error", "Booking no longer exists.");
        } else {
            $currentStatus = strtolower(trim((string)$current["payment_status"]));
            if ($currentStatus === "paid") {
                flash_set("error", "Paid bookings cannot be cancelled here. They require a refund workflow so the payment record remains accurate.");
            } elseif ($currentStatus === "cancelled") {
                flash_set("error", "This booking is already cancelled.");
            } else {
                $conn->begin_transaction();
                try {
                    $upd = $conn->prepare("UPDATE bookings SET payment_status='Cancelled' WHERE id=? AND LOWER(payment_status) <> 'paid'");
                    if (!$upd) throw new RuntimeException("Unable to prepare cancellation.");
                    $upd->bind_param("i", $id);
                    if (!$upd->execute() || $upd->affected_rows !== 1) throw new RuntimeException("Booking could not be cancelled.");
                    $upd->close();
                    $noteText = "Cancellation reason: " . $reason;
                    $ns = $conn->prepare("INSERT INTO booking_owner_notes (booking_id,admin_id,username,note) VALUES (?,?,?,?)");
                    if (!$ns) throw new RuntimeException("Unable to record cancellation reason.");
                    $ns->bind_param("iiss", $id, $adminId, $adminUsername, $noteText);
                    if (!$ns->execute()) throw new RuntimeException("Unable to record cancellation reason.");
                    $ns->close();
                    audit_booking($conn, $id, "Cancelled booking", "Owner cancelled unpaid booking. Reason: ".$reason);
                    $conn->commit();
                    flash_set("success", "Booking cancelled. The reason was recorded in the internal activity history.");
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log($e->getMessage());
                    flash_set("error", "Unable to cancel this booking.");
                }
            }
        }
        header("Location: owner_booking_view.php?id=".$id); exit();
    }
}

$stmt = $conn->prepare("SELECT id,user_id,name,email,phone,tour_name,date,time,payment,amount,payment_status,payment_reference,mpesa_receipt,created_at FROM bookings WHERE id=? LIMIT 1");
if (!$stmt) { http_response_code(500); exit("Unable to load booking."); }
$stmt->bind_param("i",$id); $stmt->execute(); $result=$stmt->get_result();
if ($result->num_rows!==1){ header("Location: owner_bookings.php"); exit(); }
$b=$result->fetch_assoc(); $stmt->close();

$status=strtolower((string)$b["payment_status"]);
$pc=in_array($status,["paid","pending","failed","cancelled"],true)?$status:"default";
$reference=trim((string)($b["mpesa_receipt"] ?: $b["payment_reference"]));
$travelTs = ($b["date"] ?? "") ? strtotime($b["date"]." ".($b["time"] ?? "00:00:00")) : false;
$travelState = !$travelTs ? "Not scheduled" : ($travelTs >= time() ? "Upcoming" : "Past");
$isTestLike = ((float)$b["amount"] <= 1.00);
$flash = $_SESSION["owner_booking_flash"] ?? null;
unset($_SESSION["owner_booking_flash"]);

$notes = [];
$ns = $conn->prepare("SELECT username,note,created_at FROM booking_owner_notes WHERE booking_id=? ORDER BY id DESC LIMIT 50");
if ($ns) { $ns->bind_param("i",$id); $ns->execute(); $nr=$ns->get_result(); while($n=$nr->fetch_assoc()) $notes[]=$n; $ns->close(); }

$audit = [];
$exists = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
if ($exists && $exists->num_rows===1) {
    $as=$conn->prepare("SELECT username,action,details,created_at FROM admin_audit_log WHERE entity_type='booking' AND entity_id=? ORDER BY id DESC LIMIT 40");
    if($as){$as->bind_param("i",$id);$as->execute();$ar=$as->get_result();while($a=$ar->fetch_assoc())$audit[]=$a;$as->close();}
}

$activity=[];
foreach($notes as $n){$activity[]=["type"=>"note","title"=>"Internal note","user"=>$n["username"],"details"=>$n["note"],"created_at"=>$n["created_at"]];}
foreach($audit as $a){$activity[]=["type"=>"audit","title"=>$a["action"],"user"=>$a["username"],"details"=>$a["details"],"created_at"=>$a["created_at"]];}
usort($activity,fn($x,$y)=>strcmp((string)$y["created_at"],(string)$x["created_at"]));
$activity=array_slice($activity,0,50);
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Booking #<?php echo $id;?> | Sprinter Tours & Safaris</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{--red:#e23333;--gold:#d6a64d;--goldSoft:#edcb7d;--text:#f7f1ed;--muted:#a69791;--border:rgba(255,255,255,.08);--green:#52bb7e}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:"DM Sans",sans-serif;color:var(--text);background:radial-gradient(circle at 10% 5%,rgba(154,21,21,.18),transparent 28%),linear-gradient(135deg,#090707,#120808 48%,#050505)}
a{color:inherit}.shell{min-height:100vh;display:grid;grid-template-columns:250px minmax(0,1fr)}.sidebar{position:sticky;top:0;height:100vh;padding:26px 18px;display:flex;flex-direction:column;background:linear-gradient(180deg,#260909,#140707 50%,#090606);border-right:1px solid rgba(230,55,55,.16)}
.brand{display:flex;align-items:center;gap:12px;padding:7px 6px 22px;border-bottom:1px solid rgba(255,255,255,.07)}.brand img{width:46px;height:46px;object-fit:contain;background:#fff;padding:4px;border-radius:12px}.brand strong{display:block;font-size:14px}.brand span{display:block;margin-top:3px;color:var(--goldSoft);font-size:8px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase}
.nav{margin-top:26px;display:grid;gap:8px}.nav-label{margin:10px 10px 5px;color:#7f706b;font-size:8px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase}.nav a{min-height:44px;padding:10px 12px;display:flex;align-items:center;gap:11px;border-radius:11px;color:#cfc2bc;text-decoration:none;font-size:11px;font-weight:700}.nav a i{width:28px;height:28px;display:grid;place-items:center;border-radius:8px;color:var(--gold);background:rgba(255,255,255,.04)}.nav a.active{color:#fff;background:linear-gradient(90deg,rgba(180,27,27,.30),rgba(105,10,10,.17));box-shadow:inset 3px 0 0 var(--red)}
.bottom{margin-top:auto;padding-top:18px;border-top:1px solid rgba(255,255,255,.07)}.profile{display:flex;align-items:center;gap:10px;padding:10px 8px}.avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;color:var(--goldSoft);background:linear-gradient(135deg,#8f1717,#d62e2e)}.profile strong{display:block;font-size:11px}.profile span{display:block;color:#857772;font-size:8px;text-transform:uppercase}
.btn{min-height:42px;padding:10px 14px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid rgba(255,255,255,.08);border-radius:11px;background:rgba(255,255,255,.03);color:#ddd0ca;text-decoration:none;font:700 10px "DM Sans",sans-serif;cursor:pointer}.main{padding:30px;min-width:0}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.eyebrow{color:var(--red);font-size:8px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase}.top h1{margin:7px 0 8px;font-family:"Playfair Display",serif;font-size:42px}.top h1 span{color:var(--red)}.top p{margin:0;color:var(--muted);font-size:12px}
.grid{display:grid;grid-template-columns:1.2fr .8fr;gap:16px}.panel{border:1px solid var(--border);border-radius:16px;background:linear-gradient(180deg,#191212,#0f0c0c);overflow:hidden}.panel-head{padding:17px 19px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;justify-content:space-between;align-items:center;gap:10px}.panel-head h2{margin:0;font-size:13px}.panel-head span{color:#8e7f79;font-size:9px}.body{padding:18px}.hero{grid-column:1/-1;padding:24px;border:1px solid rgba(226,51,51,.22);border-radius:18px;background:linear-gradient(110deg,rgba(92,11,11,.9),rgba(20,8,8,.96))}.hero-line{display:flex;justify-content:space-between;align-items:center;gap:20px}.hero h2{margin:8px 0 4px;font-family:"Playfair Display",serif;font-size:31px}.hero p{margin:0;color:#b9aaa4;font-size:11px}.amount{font-family:"Playfair Display",serif;font-size:30px;color:var(--goldSoft);white-space:nowrap}
.details{display:grid;grid-template-columns:1fr 1fr;gap:0}.item{padding:15px 0;border-bottom:1px solid rgba(255,255,255,.05)}.item:nth-child(odd){padding-right:18px}.item label{display:block;margin-bottom:6px;color:#80716c;font-size:8px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.item strong{font-size:12px}.pill{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:8px;font-weight:800;text-transform:uppercase}.paid{color:#75d7a1;background:rgba(44,135,83,.16)}.pending{color:#ebbf63;background:rgba(169,118,28,.16)}.failed,.cancelled{color:#ef8585;background:rgba(157,34,34,.16)}.default{background:rgba(255,255,255,.06)}
.notice{padding:14px;border:1px solid rgba(214,166,77,.15);border-radius:12px;background:rgba(214,166,77,.05);color:#bcae9f;font-size:10px;line-height:1.6}.actions{display:grid;gap:10px;margin-top:14px}.primary{background:linear-gradient(135deg,#a71919,#d92f2f);color:#fff}.gold{border-color:rgba(214,166,77,.25);color:var(--goldSoft)}
.flash{grid-column:1/-1;padding:13px 15px;border-radius:11px;font-size:10px;font-weight:700}.flash.success{color:#86dfab;background:rgba(39,128,75,.14);border:1px solid rgba(71,187,119,.2)}.flash.error{color:#f09b9b;background:rgba(158,36,36,.14);border:1px solid rgba(226,51,51,.2)}
.badge-test{display:inline-flex;margin-left:8px;padding:5px 8px;border-radius:999px;background:rgba(214,166,77,.12);color:var(--goldSoft);font:800 8px "DM Sans",sans-serif;vertical-align:middle}
.formbox{display:grid;gap:10px}.formbox textarea{width:100%;min-height:95px;resize:vertical;padding:12px;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:#0c0909;color:#eee;font:500 10px "DM Sans",sans-serif;outline:none}.formbox textarea:focus{border-color:rgba(214,166,77,.38)}.danger-panel{border-color:rgba(226,51,51,.18)}.danger-btn{background:rgba(174,26,26,.16);border-color:rgba(226,51,51,.26);color:#ff9b9b}.locked{opacity:.72}.micro{color:#847671;font-size:9px;line-height:1.6}
.activity{grid-column:1/-1}.timeline{display:grid}.event{position:relative;padding:15px 15px 15px 44px;border-bottom:1px solid rgba(255,255,255,.05)}.event:last-child{border-bottom:0}.event-icon{position:absolute;left:14px;top:16px;width:20px;height:20px;display:grid;place-items:center;border-radius:7px;background:rgba(214,166,77,.08);color:var(--gold);font-size:8px}.event strong{display:block;font-size:10px}.event p{margin:5px 0;color:#a99b95;font-size:9px;line-height:1.55}.event small{color:#756965;font-size:8px}.empty{padding:20px;color:#80736e;font-size:9px;text-align:center}
@media(max-width:900px){.shell{display:block}.sidebar{display:none}.main{padding:20px}.grid{grid-template-columns:1fr}.details{grid-template-columns:1fr}.item:nth-child(odd){padding-right:0}.hero-line{align-items:flex-start;flex-direction:column}}
</style></head><body><div class="shell">
<aside class="sidebar"><div class="brand"><img src="images/Wildlife Sprinter Tours & Safaris.png"><div><strong>Sprinter Tours & Safaris</strong><span>Owner Command Center</span></div></div>
<nav class="nav"><div class="nav-label">Executive</div><a href="owner_dashboard.php"><i class="fa-solid fa-crown"></i>Command Center</a><a href="owner_reports.php"><i class="fa-solid fa-chart-pie"></i>Business Reports</a><a href="owner_payments.php"><i class="fa-solid fa-credit-card"></i>Payments</a><div class="nav-label">Oversight</div><a class="active" href="owner_bookings.php"><i class="fa-solid fa-calendar-check"></i>Bookings</a><a href="owner_customers.php"><i class="fa-solid fa-users"></i>Customers</a><a href="owner_messages.php"><i class="fa-solid fa-envelope"></i>Messages</a><a href="owner_audit.php"><i class="fa-solid fa-shield-halved"></i>Audit Activity</a></nav>
<div class="bottom"><div class="profile"><div class="avatar"><i class="fa-solid fa-crown"></i></div><div><strong><?php echo oe($adminUsername);?></strong><span>Owner</span></div></div><a class="btn" style="width:100%" href="admin_logout.php"><i class="fa-solid fa-right-from-bracket"></i>Sign Out</a></div></aside>
<main class="main"><header class="top"><div><div class="eyebrow">Booking Intelligence</div><h1>Booking <span>#<?php echo $id;?></span></h1><p>Complete reservation, customer, travel and payment record.</p></div><a class="btn" href="owner_bookings.php"><i class="fa-solid fa-arrow-left"></i>Back to Bookings</a></header>
<div class="grid">
<?php if($flash):?><div class="flash <?php echo oe($flash["type"]);?>"><?php echo oe($flash["message"]);?></div><?php endif;?>
<section class="hero"><div class="hero-line"><div><div class="eyebrow"><?php echo oe($travelState);?> reservation</div><h2><?php echo oe($b["tour_name"] ?: "Tour Booking");?></h2><p><?php echo oe($b["name"]);?> · <?php echo $b["date"]?date("d M Y",strtotime($b["date"])):"No travel date";?> <?php echo oe($b["time"]??"");?> <?php if($isTestLike):?><span class="badge-test">LOW-VALUE / TEST-LIKE</span><?php endif;?></p></div><div class="amount">KES <?php echo number_format((float)$b["amount"],0);?></div></div></section>
<section class="panel"><div class="panel-head"><h2>Customer & Travel</h2><span class="pill <?php echo $pc;?>"><?php echo oe($b["payment_status"]);?></span></div><div class="body"><div class="details">
<div class="item"><label>Customer</label><strong><?php echo oe($b["name"]);?></strong></div><div class="item"><label>User ID</label><strong><?php echo $b["user_id"] ? "#".(int)$b["user_id"] : "Guest / unavailable";?></strong></div>
<div class="item"><label>Email</label><strong><?php echo oe($b["email"] ?: "—");?></strong></div><div class="item"><label>Phone</label><strong><?php echo oe($b["phone"] ?: "—");?></strong></div>
<div class="item"><label>Tour</label><strong><?php echo oe($b["tour_name"] ?: "—");?></strong></div><div class="item"><label>Travel status</label><strong><?php echo oe($travelState);?></strong></div>
<div class="item"><label>Travel date</label><strong><?php echo $b["date"]?date("d M Y",strtotime($b["date"])):"—";?></strong></div><div class="item"><label>Travel time</label><strong><?php echo oe($b["time"] ?: "—");?></strong></div>
</div></div></section>
<section class="panel"><div class="panel-head"><h2>Payment Record</h2><span>Financial record</span></div><div class="body"><div class="details">
<div class="item"><label>Amount</label><strong>KES <?php echo number_format((float)$b["amount"],2);?></strong></div><div class="item"><label>Method</label><strong><?php echo oe($b["payment"] ?: "—");?></strong></div>
<div class="item"><label>Payment status</label><strong><span class="pill <?php echo $pc;?>"><?php echo oe($b["payment_status"]);?></span></strong></div><div class="item"><label>Reference</label><strong><?php echo oe($reference ?: "Not available");?></strong></div>
<div class="item"><label>Created</label><strong><?php echo $b["created_at"]?date("d M Y H:i",strtotime($b["created_at"])):"—";?></strong></div><div class="item"><label>Booking ID</label><strong>#<?php echo $id;?></strong></div></div>
<div class="notice"><i class="fa-solid fa-shield-halved"></i> Payment amounts and paid status are intentionally not editable here. Confirmed financial states must come from the payment flow.</div><div class="actions"><a class="btn gold" href="owner_payments.php?search=<?php echo urlencode((string)$id);?>"><i class="fa-solid fa-credit-card"></i>Find in Payment Ledger</a><?php if($status==="paid"):?><a class="btn primary" href="owner_receipt.php?id=<?php echo $id;?>" target="_blank"><i class="fa-solid fa-receipt"></i>Open Receipt</a><?php endif;?></div></div></section>
<section class="panel"><div class="panel-head"><h2>Internal Notes</h2><span>Owner only</span></div><div class="body"><form class="formbox" method="post"><input type="hidden" name="csrf_token" value="<?php echo oe($csrf);?>"><input type="hidden" name="booking_id" value="<?php echo $id;?>"><input type="hidden" name="action" value="add_note"><textarea name="note" maxlength="1500" placeholder="Add an internal note: customer request, call outcome, hotel arrangement, pickup instructions..."></textarea><button class="btn gold" type="submit"><i class="fa-solid fa-note-sticky"></i>Save Internal Note</button></form><p class="micro">Notes are private to the Owner system and are added to this booking's activity history.</p></div></section>
<section class="panel danger-panel"><div class="panel-head"><h2>Booking Control</h2><span>Protected action</span></div><div class="body"><?php if($status==="paid"):?><div class="notice locked"><i class="fa-solid fa-lock"></i> This booking is paid. Cancellation is locked here because a paid booking needs a proper refund workflow to keep the financial record accurate.</div><?php elseif($status==="cancelled"):?><div class="notice locked"><i class="fa-solid fa-ban"></i> This booking has already been cancelled.</div><?php else:?><form class="formbox" method="post" onsubmit="return confirm('Cancel booking #<?php echo $id;?>? This will record the action in the audit trail.');"><input type="hidden" name="csrf_token" value="<?php echo oe($csrf);?>"><input type="hidden" name="booking_id" value="<?php echo $id;?>"><input type="hidden" name="action" value="cancel_booking"><textarea name="reason" maxlength="500" required placeholder="Reason for cancellation..."></textarea><button class="btn danger-btn" type="submit"><i class="fa-solid fa-ban"></i>Cancel Unpaid Booking</button></form><p class="micro">This changes only an unpaid booking to Cancelled and records who performed the action and why.</p><?php endif;?></div></section>
<section class="panel activity"><div class="panel-head"><h2>Booking Activity</h2><span><?php echo count($activity);?> recent events</span></div><div class="timeline"><?php if($activity):foreach($activity as $ev):?><div class="event"><div class="event-icon"><i class="fa-solid <?php echo $ev["type"]==="note"?"fa-note-sticky":"fa-shield-halved";?>"></i></div><strong><?php echo oe($ev["title"]);?></strong><p><?php echo nl2br(oe($ev["details"]));?></p><small><?php echo oe($ev["user"] ?: "System");?> · <?php echo $ev["created_at"]?date("d M Y H:i",strtotime($ev["created_at"])):"—";?></small></div><?php endforeach;else:?><div class="empty">No internal notes or audit activity recorded for this booking yet.</div><?php endif;?></div></section>
</div></main></div></body></html>
