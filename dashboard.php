<?php
require 'config.php';

$employeeId = $_GET['employee_id'];

$stmt = $pdo->prepare("
    SELECT
        SUM(DATEDIFF(SECOND, clock_in_time,
            COALESCE(clock_out_time, SYSUTCDATETIME())
        )) / 3600.0 AS total_hours
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_in_time >= DATEADD(DAY, -7, SYSUTCDATETIME())
");

$stmt->execute([$employeeId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Weekly Hours</h2>";
echo "Total Hours (Last 7 Days): " . round($result['total_hours'], 2);
?>
