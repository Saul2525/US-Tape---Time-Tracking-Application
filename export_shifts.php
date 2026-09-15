<?php
session_start();
require 'config.php';

if (!isset($_SESSION['role_id'])) {
    die("Access denied.");
}

$roleStmt = $pdo->prepare("
    SELECT can_edit_others
    FROM ROLES
    WHERE role_id = ?
    LIMIT 1
");
$roleStmt->execute([$_SESSION['role_id']]);
$currentRole = $roleStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentRole || !$currentRole['can_edit_others']) {
    die("Access denied.");
}

$employeeId = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int) $_GET['employee_id'] : null;

$employeeIds = [];
if (isset($_GET['employee_ids']) && is_array($_GET['employee_ids'])) {
    foreach ($_GET['employee_ids'] as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $employeeIds[] = $id;
        }
    }
    $employeeIds = array_values(array_unique($employeeIds));
}

$search = trim($_GET['search'] ?? '');

$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = date('Y-m-d', strtotime('-6 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = date('Y-m-d');
}
if ($fromDate > $toDate) {
    $tmp = $fromDate;
    $fromDate = $toDate;
    $toDate = $tmp;
}

$fromBoundary = $fromDate . ' 00:00:00';
$toBoundary = $toDate . ' 23:59:59';

$sql = "
    SELECT
        e.employee_id,
        e.first_name,
        e.last_name,
        e.email,
        w.clock_in_time,
        w.clock_out_time,
        w.approved,
        w.is_edited,
        w.manager_note
    FROM WORK_TIMES w
    JOIN EMPLOYEES e ON e.employee_id = w.employee_id
    WHERE w.clock_in_time BETWEEN ? AND ?
";
$params = [$fromBoundary, $toBoundary];

if ($employeeId) {
    $sql .= " AND w.employee_id = ? ";
    $params[] = $employeeId;
} elseif (!empty($employeeIds)) {
    $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
    $sql .= " AND w.employee_id IN ($placeholders) ";
    foreach ($employeeIds as $id) {
        $params[] = $id;
    }
} elseif ($search) {
    $sql .= " AND CONCAT(e.first_name,' ',e.last_name) LIKE ? ";
    $params[] = "%" . $search . "%";
}

$sql .= " ORDER BY e.first_name ASC, e.last_name ASC, w.clock_in_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "shifts_{$fromDate}_to_{$toDate}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Employee', 'Email', 'Clock In', 'Clock Out', 'Hours', 'Approved', 'Edited', 'Note'], ',', '"', '\\');

function writeEmployeeTotalRow($out, $employeeLabel, $totalSeconds) {
    $totalHours = round($totalSeconds / 3600, 2);
    fputcsv($out, ['', '', '', 'Total Hours (' . $employeeLabel . ')', $totalHours, '', '', ''], ',', '"', '\\');
    fputcsv($out, [], ',', '"', '\\');
}

$currentEmployeeId = null;
$currentEmployeeLabel = '';
$employeeTotalSeconds = 0;

foreach ($rows as $row) {
    if ($currentEmployeeId !== null && $row['employee_id'] !== $currentEmployeeId) {
        writeEmployeeTotalRow($out, $currentEmployeeLabel, $employeeTotalSeconds);
        $employeeTotalSeconds = 0;
    }
    $currentEmployeeId = $row['employee_id'];
    $currentEmployeeLabel = $row['first_name'] . ' ' . $row['last_name'];

    $clockIn = date('Y-m-d H:i', strtotime($row['clock_in_time']));
    $clockOut = $row['clock_out_time'] ? date('Y-m-d H:i', strtotime($row['clock_out_time'])) : 'Open';

    $hours = '';
    if ($row['clock_out_time']) {
        $seconds = strtotime($row['clock_out_time']) - strtotime($row['clock_in_time']);
        $hours = round($seconds / 3600, 2);
        $employeeTotalSeconds += $seconds;
    }

    fputcsv($out, [
        $row['first_name'] . ' ' . $row['last_name'],
        $row['email'],
        $clockIn,
        $clockOut,
        $hours,
        $row['approved'] ? 'Yes' : 'No',
        $row['is_edited'] ? 'Yes' : 'No',
        $row['manager_note'] ?? '',
    ], ',', '"', '\\');
}

if ($currentEmployeeId !== null) {
    writeEmployeeTotalRow($out, $currentEmployeeLabel, $employeeTotalSeconds);
}

fclose($out);
exit;
