<?php
session_start();

// Check for login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Example credentials
    $correct_username = 'user';
    $correct_password = 'pass';

    // Simple authentication check
    if ($username === $correct_username && $password === $correct_password) {
        $_SESSION['logged_in'] = true;
        header('Location: dashboard.php'); // Redirect to dashboard upon successful login
        exit;
    } else {
        $error = 'Invalid credentials!';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login Form</h2>
    <?php if (isset($error)): ?>
        <p style="color:red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <input type="submit" value="Login">
    </form>
</body>
</html>