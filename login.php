<?php
include "config.php";

if(isset($_POST['login'])){
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if($result->num_rows > 0){
        $user = $result->fetch_assoc();

        if(password_verify($password, $user['password'])){
            $_SESSION['user_id'] = $user['id']; // FIXED
            header("Location: booking.php");
            exit();
        }else{
            echo "<script>alert('Wrong password');</script>";
        }
    }else{
        echo "<script>alert('User not found');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Login</title>
<link rel="stylesheet" href="style.css">

</head>
<body>

<header>
<h1>SPRINTER WILDLIFE TOURS</h1>
</header>

<section class="login-page">

<h2>Login</h2>

<form method="POST">

<input type="text" name="username" placeholder="Username" required>

<input type="password" name="password" placeholder="Password" required>

<button name="login">Login</button>

</form>

</section>

</body>
</html>