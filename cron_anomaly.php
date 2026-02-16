<?php
require 'config.php';

$stmt = $pdo->query("
    SELECT employee_id, clock_in_time
    FROM WORK_TIMES
    WHERE clock_out_time IS NULL
    AND TIMESTAMPDIFF(HOUR, clock_in_time, UTC_TIMESTAMP()) > 10
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    echo "ALERT: Employee {$row['employee_id']} open shift since {$row['clock_in_time']} \n";
}
?>
