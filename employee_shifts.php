<?php
session_start();
require 'config.php';

$employee_id = $_GET['employee_id'] ?? null;
if (!$employee_id) die("Employee not specified.");

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

// Get employee info
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM EMPLOYEES WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
if (!$employee) die("Employee not found.");

// Time boundaries for selected date range
$fromBoundary = $fromDate . ' 00:00:00';
$toBoundary = $toDate . ' 23:59:59';

// Get work times
$stmt = $pdo->prepare("
    SELECT work_time_id, clock_in_time, clock_out_time, approved, is_edited, manager_note
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_in_time BETWEEN ? AND ?
    ORDER BY clock_in_time DESC
");
$stmt->execute([$employee_id, $fromBoundary, $toBoundary]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total hours for selected range
$totalHoursStmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, clock_in_time, IFNULL(clock_out_time, UTC_TIMESTAMP()))), 0) AS total_minutes
    FROM WORK_TIMES
    WHERE employee_id = ?
    AND clock_in_time BETWEEN ? AND ?
");
$totalHoursStmt->execute([$employee_id, $fromBoundary, $toBoundary]);
$totalMinutes = (int) ($totalHoursStmt->fetch(PDO::FETCH_ASSOC)['total_minutes'] ?? 0);
$totalHours = round($totalMinutes / 60, 2);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?> - Shifts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            background: #f7f8fa;
            color: #1f2937;
        }
        .page-title {
            margin: 0 0 6px 0;
            font-size: 30px;
        }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .header-content {
            flex: 1;
        }
        .export-button {
            border: 0;
            background: #1d4ed8;
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 4px;
        }
        .subtext {
            margin: 0 0 20px 0;
            color: #4b5563;
            font-size: 15px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            margin-bottom: 14px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
            align-items: end;
        }
        .filter-group label {
            display: block;
            font-size: 13px;
            color: #4b5563;
            margin-bottom: 4px;
        }
        .filter-group input[type=date] {
            padding: 8px 10px;
            border: 1px solid #cfd5df;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-button {
            border: 0;
            background: #2563eb;
            color: #fff;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .hours-summary {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        .hours-value {
            color: #1f4e79;
            font-size: 24px;
            margin-left: 4px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 12px 10px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background: #f3f4f6;
            font-size: 14px;
        }
        .time-input {
            width: 180px;
            padding: 10px 12px;
            font-size: 15px;
            border: 1px solid #cfd5df;
            border-radius: 8px;
            font-family: Arial, sans-serif;
        }
        .input-help {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
        }
        .note-input {
            width: 160px;
            padding: 10px 12px;
            font-size: 15px;
            border: 1px solid #cfd5df;
            border-radius: 8px;
            font-family: Arial, sans-serif;
        }
        .approved-cell input[type=checkbox] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-edited {
            background: #fff4db;
            color: #915f00;
        }
        .status-original {
            background: #e8f5e9;
            color: #1b5e20;
        }
        .save-button {
            border: 0;
            background: #1f4e79;
            color: #fff;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .save-button:hover {
            background: #163a5b;
        }
        .back-link {
            margin-top: 14px;
            display: inline-block;
            text-decoration: none;
        }
        .back-button {
            border: 0;
            background: #4b5563;
            color: #fff;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="header-row">
    <div class="header-content">
        <h2 class="page-title"><?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?> - Shifts</h2>
        <p class="subtext">Edit times in a simple format: <strong>YYYY-MM-DD HH:MM</strong> (example: 2026-04-21 08:30)</p>
    </div>
    <a href="export_shifts.php?employee_id=<?php echo (int) $employee_id; ?>&from=<?php echo urlencode($fromDate); ?>&to=<?php echo urlencode($toDate); ?>">
        <button class="export-button" type="button">Export to Excel</button>
    </a>
</div>

<div class="card">
    <form method="GET" class="filter-row">
        <input type="hidden" name="employee_id" value="<?php echo (int) $employee_id; ?>">

        <div class="filter-group">
            <label for="from">From</label>
            <input id="from" type="date" name="from" value="<?php echo htmlspecialchars($fromDate); ?>">
        </div>

        <div class="filter-group">
            <label for="to">To</label>
            <input id="to" type="date" name="to" value="<?php echo htmlspecialchars($toDate); ?>">
        </div>

        <button class="filter-button" type="submit">Apply Time Range</button>
    </form>
</div>

<div class="card">
    <p class="hours-summary">
        Total Hours in Selected Range:
        <span class="hours-value"><?php echo htmlspecialchars((string) $totalHours); ?></span>
    </p>
</div>

<div class="card">
    <form method="POST" action="update_shift.php">
        <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
        <table>
            <tr>
                <th>Clock In</th>
                <th>Clock Out</th>
                <th>Approved</th>
                <th>Edited</th>
                <th>Note</th>
                <th>Action</th>
            </tr>
            <?php foreach ($shifts as $shift): ?>
            <tr>
                <td>
                    <input
                        class="time-input"
                        type="text"
                        name="clock_in[<?php echo $shift['work_time_id']; ?>]"
                        value="<?php echo date('Y-m-d H:i', strtotime($shift['clock_in_time'])); ?>"
                        pattern="\d{4}-\d{2}-\d{2} \d{2}:\d{2}"
                        title="Use format YYYY-MM-DD HH:MM"
                        required
                    >
                    <span class="input-help">Example: 2026-04-21 08:30</span>
                </td>
                <td>
                    <input
                        class="time-input"
                        type="text"
                        name="clock_out[<?php echo $shift['work_time_id']; ?>]"
                        value="<?php echo $shift['clock_out_time'] ? date('Y-m-d H:i', strtotime($shift['clock_out_time'])) : ''; ?>"
                        pattern="\d{4}-\d{2}-\d{2} \d{2}:\d{2}"
                        title="Use format YYYY-MM-DD HH:MM"
                    >
                    <span class="input-help">Leave blank for open shift</span>
                </td>
                <td class="approved-cell">
                    <input type="checkbox" name="approved[<?php echo $shift['work_time_id']; ?>]"
                           <?php echo $shift['approved'] ? 'checked' : ''; ?>>
                </td>
                <td>
                    <?php if ($shift['is_edited']): ?>
                        <span class="status-pill status-edited">Edited</span>
                    <?php else: ?>
                        <span class="status-pill status-original">Original</span>
                    <?php endif; ?>
                </td>
                <td>
                    <input
                        class="note-input"
                        type="text"
                        name="note[<?php echo $shift['work_time_id']; ?>]"
                        value="<?php echo htmlspecialchars($shift['manager_note'] ?? ''); ?>"
                        maxlength="255"
                        placeholder="e.g. late, left early"
                    >
                </td>
                <td><button class="save-button" type="submit" name="save_id" value="<?php echo $shift['work_time_id']; ?>">Save Changes</button></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </form>
</div>

<a class="back-link" href="manager_dashboard.php"><button class="back-button" type="button">Back to Manager Dashboard</button></a>

</body>
</html>