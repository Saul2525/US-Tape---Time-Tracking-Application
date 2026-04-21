<?php
require 'config.php';

/*
|--------------------------------------------------------------------------
| 1. Validate PIN Input
|--------------------------------------------------------------------------
*/

$pin = trim($_POST['pin'] ?? '');

if (!preg_match('/^\d{5}$/', $pin)) {
    die("Invalid PIN format.");
}

/*
|--------------------------------------------------------------------------
| 2. Lookup Employee by PIN (NO EMAIL)
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT employee_id, first_name, last_name
    FROM EMPLOYEES
    WHERE pin = ? AND is_active = 1
    LIMIT 1
");
$stmt->execute([$pin]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Invalid PIN.");
}

$employeeId = $employee['employee_id'];

/*
|--------------------------------------------------------------------------
| 3. Capture Location + IP
|--------------------------------------------------------------------------
*/

$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;
$ip  = $_SERVER['REMOTE_ADDR'] ?? null;

/*
|--------------------------------------------------------------------------
| 4. Check for Open Shift
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT work_time_id
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_out_time IS NULL
    LIMIT 1
");
$stmt->execute([$employeeId]);

$openShift = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| 5. Toggle Clock In / Out
|--------------------------------------------------------------------------
*/

if ($openShift) {

    // CLOCK OUT
    $pdo->prepare("
        UPDATE WORK_TIMES
        SET clock_out_time = UTC_TIMESTAMP(),
            clock_out_lat = ?,
            clock_out_lng = ?,
            clock_out_ip = ?
        WHERE work_time_id = ?
    ")->execute([
        $lat,
        $lng,
        $ip,
        $openShift['work_time_id']
    ]);

    $message = "Clocked OUT successfully.";

} else {

    // CLOCK IN
    $pdo->prepare("
        INSERT INTO WORK_TIMES
        (employee_id, clock_in_time, clock_in_lat, clock_in_lng, clock_in_ip)
        VALUES (?, UTC_TIMESTAMP(), ?, ?, ?)
    ")->execute([
        $employeeId,
        $lat,
        $lng,
        $ip
    ]);

    $message = "Clocked IN successfully.";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Status</title>
    <meta http-equiv="refresh" content="3;url=index.php">
</head>
<body style="text-align:center; font-family: Arial;">

    <h2><?php echo htmlspecialchars($message); ?></h2>
    <p>Redirecting...</p>

</body>
</html>