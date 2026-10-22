<?php
require 'config.php';

$stmt = $pdo->query("
    SELECT employee_id, clock_in_time
    FROM WORK_TIMES
    WHERE clock_out_time IS NULL
    AND DATEDIFF(SECOND, clock_in_time, SYSUTCDATETIME()) > 36000
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "ALERT: Employee {$row['employee_id']} open shift since {$row['clock_in_time']} \n";
}
?>
