<?php
require 'config.php';

$email = $_GET['email'] ?? null;

if (!$email) {
    die("No employee specified.");
}

// Get employee
$stmt = $pdo->prepare("SELECT * FROM EMPLOYEES WHERE email = ?");
$stmt->execute([$email]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found.");
}

$employeeId = $employee['employee_id'];

// Total completed hours (approved + pending)
$stmt = $pdo->prepare("
    SELECT 
        SUM(TIMESTAMPDIFF(SECOND, clock_in_time, clock_out_time)) as total_seconds
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_out_time IS NOT NULL
");
$stmt->execute([$employeeId]);
$resultAll = $stmt->fetch(PDO::FETCH_ASSOC);

$totalSecondsAll = $resultAll['total_seconds'] ?? 0;
$totalHoursAll = round($totalSecondsAll / 3600, 2);

// Calculate total approved hours
$stmt = $pdo->prepare("
    SELECT 
        SUM(TIMESTAMPDIFF(SECOND, clock_in_time, clock_out_time)) as total_seconds
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND approved = 1
    AND clock_out_time IS NOT NULL
");
$stmt->execute([$employeeId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$totalSeconds = $result['total_seconds'] ?? 0;
$totalHours = round($totalSeconds / 3600, 2);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Profile</title>
</head>
<body>

    <h1>Employee Profile</h1>

    <h3><?php echo htmlspecialchars($employee['first_name'] . " " . $employee['last_name']); ?></h3>

    <p><strong>Total Hours Logged:</strong> <?php echo $totalHoursAll; ?> hours</p>

    <p><strong>Total Approved Hours:</strong> <?php echo $totalHours; ?> hours</p>

    <br><br>

    <a href="index.php">
        <button>Go Back Home</button>
    </a>

</body>
</html>
