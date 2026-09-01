<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Fetch user by email
    $stmt = $pdo->prepare("
        SELECT employee_id, first_name, password, role_id
        FROM EMPLOYEES
        WHERE email = ? 
        AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validate login
    if (!$user) {
        $error = "Invalid credentials.";
    } 
    elseif ($user['password'] !== $password) {  // Plain text comparison
        $error = "Invalid credentials.";
    }
    elseif (!in_array((int) $user['role_id'], [2, 3])) {  // Manager or Admin
        $error = "Access denied.";
    }
    else {
        // Set session
        $_SESSION['user_id']   = $user['employee_id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['role_id']   = $user['role_id'];

        // Redirect to manager page
        header("Location: manager_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manager Login</title>
</head>
<body style="text-align:center; margin-top:100px; font-family:Arial;">

    <h2>Manager Login</h2>

    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit" style="padding:10px 30px;">Login</button>
    </form>

</body>
</html>