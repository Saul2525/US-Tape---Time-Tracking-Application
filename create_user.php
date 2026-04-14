<?php
require 'config.php';

/*
|--------------------------------------------------------------------------
| 1. Validate Inputs
|--------------------------------------------------------------------------
*/

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$roleId    = $_POST['role_id'] ?? null;

if (!$firstName || !$lastName || !$email || !$roleId) {
    die("All fields are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}

/*
|--------------------------------------------------------------------------
| 2. Generate Unique 5-Digit PIN
|--------------------------------------------------------------------------
*/

function generateUniquePin($pdo) {

    do {
        $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("SELECT employee_id FROM EMPLOYEES WHERE pin = ?");
        $stmt->execute([$pin]);

        $exists = $stmt->fetch();

    } while ($exists);

    return $pin;
}

$pin = generateUniquePin($pdo);

/*
|--------------------------------------------------------------------------
| 3. Insert Employee
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO EMPLOYEES
    (first_name, last_name, email, pin, role_id, is_active)
    VALUES (?, ?, ?, ?, ?, 1)
");

$stmt->execute([
    $firstName,
    $lastName,
    $email,
    $pin,
    $roleId
]);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Created</title>
</head>
<body style="text-align:center; font-family: Arial;">

    <h2>Employee Created Successfully</h2>
    <p><strong>Generated PIN:</strong> <?php echo htmlspecialchars($pin); ?></p>

</body>
</html>