<?php
require 'config.php';

$employeeId = $_GET['employee_id'];

$stmt = $pdo->prepare("
    SELECT 
        SUM(TIMESTAMPDIFF(SECOND, clock_in_time, 
            IFNULL(clock_out_time, UTC_TIMESTAMP())
        )) / 3600 AS total_hours
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_in_time >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
");

$stmt->execute([$employeeId]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Weekly Hours</h2>";
echo "Total Hours (Last 7 Days): " . round($result['total_hours'], 2);
?>
