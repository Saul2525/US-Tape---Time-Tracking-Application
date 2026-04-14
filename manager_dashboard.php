<?php
session_start();
require 'config.php';

// Only admins or managers can access
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
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
        .top-buttons { margin-top: 20px; }
    </style>
</head>
<body>
    <h2>Manager Dashboard</h2>

    <div class="top-buttons">
        <!-- Back to Employee Clock-In Home -->
        <a href="index.php">
            <button style="background:#4CAF50; color:white;">Back to Employee Clock-In</button>
        </a>
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