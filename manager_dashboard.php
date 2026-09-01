<?php
session_start();
require 'config.php';

if (!isset($_SESSION['role_id'])) {
    die("Access denied.");
}

// Load permission flags from role table instead of hardcoded IDs.
$roleStmt = $pdo->prepare("
    SELECT role_name, can_edit_others, can_manage_users
    FROM ROLES
    WHERE role_id = ?
    LIMIT 1
");
$roleStmt->execute([$_SESSION['role_id']]);
$currentRole = $roleStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentRole || !$currentRole['can_edit_others']) {
    die("Access denied.");
}

// Handle filter inputs
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to   = $_GET['to'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

// Fetch employees with optional search
$sql = "SELECT * FROM EMPLOYEES WHERE is_active = 1";
$params = [];

if ($search) {
    $sql .= " AND CONCAT(first_name,' ',last_name) LIKE ? ";
    $params[] = "%" . $search . "%";
}

$sql .= " ORDER BY first_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manager Dashboard</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background: #f4f4f4; }
        button { padding:5px 10px; margin-right:5px; }
        input[type=text], input[type=date] { padding: 5px; margin-right: 5px; }
        .top-row { margin-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .top-buttons { display: flex; align-items: center; gap: 8px; }
        .export-button {
            border: 0;
            background: #1d4ed8;
            color: #fff;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h2>Manager Dashboard</h2>

    <div class="top-row">
        <div class="top-buttons">
            <!-- Back to Employee Clock-In Home -->
            <a href="index.php">
                <button style="background:#4CAF50; color:white;">Back to Employee Clock-In</button>
            </a>
            <?php if (!empty($currentRole['can_manage_users'])): ?>
                <a href="admin.php">
                    <button style="background:#1f4e79; color:white;">Employee Management</button>
                </a>
            <?php endif; ?>
        </div>
        <button class="export-button" type="button" title="Coming soon">Export to Excell</button>
    </div>

    <form method="GET" style="margin-top:15px;">
        From: <input type="date" name="from" value="<?php echo $from; ?>">
        To: <input type="date" name="to" value="<?php echo $to; ?>">
        Search Employee: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Email</th>
                <th>View Shifts</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                    <td>
                        <a href="employee_shifts.php?employee_id=<?php echo $emp['employee_id']; ?>">
                            <button>View Shifts</button>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($employees)) echo "<tr><td colspan='3'>No employees found</td></tr>"; ?>
        </tbody>
    </table>
</body>
</html>