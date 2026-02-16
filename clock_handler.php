<?php
require 'config.php';

$email = $_POST['email'];
$pin = $_POST['pin'];
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'];

// Get employee
$stmt = $pdo->prepare("SELECT * FROM EMPLOYEES WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

if (!password_verify($pin, $employee['pin'])) {
    die("Invalid PIN.");
}

$employeeId = $employee['employee_id'];

// Check for open shift
$stmt = $pdo->prepare("
    SELECT work_time_id 
    FROM WORK_TIMES 
    WHERE employee_id = ? 
    AND clock_out_time IS NULL
");
$stmt->execute([$employeeId]);

if ($stmt->rowCount() > 0) {

    // CLOCK OUT
    $pdo->prepare("
        UPDATE WORK_TIMES
        SET clock_out_time = UTC_TIMESTAMP(),
            clock_out_lat = ?,
            clock_out_lng = ?,
            clock_out_ip = ?
        WHERE employee_id = ?
        AND clock_out_time IS NULL
    ")->execute([$lat, $lng, $ip, $employeeId]);

    $message = "Clocked OUT successfully.";

} else {

    // CLOCK IN
    $pdo->prepare("
        INSERT INTO WORK_TIMES
        (employee_id, clock_in_time, clock_in_lat, clock_in_lng, clock_in_ip)
        VALUES (?, UTC_TIMESTAMP(), ?, ?, ?)
    ")->execute([$employeeId, $lat, $lng, $ip]);

    $message = "Clocked IN successfully.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Status</title>
</head>
<body>

    <h2><?php echo $message; ?></h2>

    <br><br>

    <a href="profile.php?email=<?php echo urlencode($employee['email']); ?>">
        <button style="padding:10px 20px; margin-right:10px;">
            View My Profile
        </button>
    </a>

    <a href="index.php">
        <button style="padding:10px 20px;">
            Go Back Home
        </button>
    </a>

</body>
</html>

