<?php
session_start();
require 'config.php';

$employee_id = $_GET['employee_id'] ?? null;
if (!$employee_id) die("Employee not specified.");

// Get employee info
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM EMPLOYEES WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();
if (!$employee) die("Employee not found.");

// Get work times
$stmt = $pdo->prepare("
    SELECT work_time_id, clock_in_time, clock_out_time, approved, is_edited
    FROM WORK_TIMES
    WHERE employee_id = ?
    ORDER BY clock_in_time DESC
");
$stmt->execute([$employee_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?> - Shifts</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        input[type=datetime-local] { width: 150px; }
    </style>
</head>
<body>
<h2><?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?> - Shifts</h2>

<form method="POST" action="update_shift.php">
    <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
    <table>
        <tr>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Approved</th>
            <th>Edited</th>
            <th>Action</th>
        </tr>
        <?php foreach ($shifts as $shift): ?>
        <tr>
            <td>
                <input type="datetime-local" name="clock_in[<?php echo $shift['work_time_id']; ?>]" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime($shift['clock_in_time'])); ?>">
            </td>
            <td>
                <input type="datetime-local" name="clock_out[<?php echo $shift['work_time_id']; ?>]" 
                       value="<?php echo $shift['clock_out_time'] ? date('Y-m-d\TH:i', strtotime($shift['clock_out_time'])) : ''; ?>">
            </td>
            <td>
                <input type="checkbox" name="approved[<?php echo $shift['work_time_id']; ?>]" 
                       <?php echo $shift['approved'] ? 'checked' : ''; ?>>
            </td>
            <td><?php echo $shift['is_edited'] ? 'Yes' : 'No'; ?></td>
            <td><button type="submit" name="save_id" value="<?php echo $shift['work_time_id']; ?>">Save</button></td>
        </tr>
        <?php endforeach; ?>
    </table>
</form>

<br>
<a href="manager_dashboard.php"><button>Back to Manager Dashboard</button></a>

</body>
</html>