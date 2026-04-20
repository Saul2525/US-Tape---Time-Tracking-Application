<?php
session_start();
require 'config.php';

if (!isset($_SESSION['role_id'])) {
    die("Access denied.");
}

$roleStmt = $pdo->prepare("
    SELECT role_name, can_manage_users
    FROM ROLES
    WHERE role_id = ?
    LIMIT 1
");
$roleStmt->execute([$_SESSION['role_id']]);
$currentRole = $roleStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentRole || !$currentRole['can_manage_users']) {
    die("Access denied.");
}

$messages = [];
$errors = [];

function generateUniquePin($pdo) {
    do {
        $pin = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT employee_id FROM EMPLOYEES WHERE pin = ? LIMIT 1");
        $stmt->execute([$pin]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    } while ($exists);

    return $pin;
}

function hasEmployeesPasswordColumn($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM EMPLOYEES LIKE 'password'");
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

$passwordColumnExists = hasEmployeesPasswordColumn($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_employee') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $manualPin = trim($_POST['pin'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($firstName === '' || $lastName === '' || $email === '' || $roleId <= 0) {
            $errors[] = "First name, last name, email, and role are required.";
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please provide a valid email address.";
        }

        $roleCheck = $pdo->prepare("SELECT role_id FROM ROLES WHERE role_id = ? LIMIT 1");
        $roleCheck->execute([$roleId]);
        if (!$roleCheck->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "Selected role does not exist.";
        }

        $employeeExistsStmt = $pdo->prepare("SELECT employee_id FROM EMPLOYEES WHERE email = ? LIMIT 1");
        $employeeExistsStmt->execute([$email]);
        if ($employeeExistsStmt->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = "An employee with this email already exists.";
        }

        if ($manualPin !== '' && !preg_match('/^\d{5}$/', $manualPin)) {
            $errors[] = "PIN must be exactly 5 digits.";
        }

        if ($manualPin !== '') {
            $pinExistsStmt = $pdo->prepare("SELECT employee_id FROM EMPLOYEES WHERE pin = ? LIMIT 1");
            $pinExistsStmt->execute([$manualPin]);
            if ($pinExistsStmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "That PIN is already in use.";
            }
        }

        if ($passwordColumnExists && $password === '') {
            $errors[] = "Password is required for this database setup.";
        }

        if (empty($errors)) {
            $pin = ($manualPin !== '') ? $manualPin : generateUniquePin($pdo);

            if ($passwordColumnExists) {
                $insertStmt = $pdo->prepare("
                    INSERT INTO EMPLOYEES (first_name, last_name, email, pin, password, role_id, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $insertStmt->execute([$firstName, $lastName, $email, $pin, $password, $roleId]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO EMPLOYEES (first_name, last_name, email, pin, role_id, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $insertStmt->execute([$firstName, $lastName, $email, $pin, $roleId]);
            }

            $messages[] = "Employee created successfully. Assigned PIN: {$pin}";
        }
    }

    if ($action === 'deactivate_employee') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $errors[] = "Invalid employee selected for deactivation.";
        } elseif (!empty($_SESSION['user_id']) && $employeeId === (int) $_SESSION['user_id']) {
            $errors[] = "You cannot deactivate your own account.";
        } else {
            $deactivateStmt = $pdo->prepare("UPDATE EMPLOYEES SET is_active = 0 WHERE employee_id = ?");
            $deactivateStmt->execute([$employeeId]);
            $messages[] = "Employee deactivated successfully.";
        }
    }

    if ($action === 'activate_employee') {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);

        if ($employeeId <= 0) {
            $errors[] = "Invalid employee selected for activation.";
        } else {
            $activateStmt = $pdo->prepare("UPDATE EMPLOYEES SET is_active = 1 WHERE employee_id = ?");
            $activateStmt->execute([$employeeId]);
            $messages[] = "Employee activated successfully.";
        }
    }
}

$rolesStmt = $pdo->query("SELECT role_id, role_name FROM ROLES ORDER BY role_name");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$employeesStmt = $pdo->query("
    SELECT e.employee_id, e.first_name, e.last_name, e.email, e.pin, e.is_active, r.role_name
    FROM EMPLOYEES e
    LEFT JOIN ROLES r ON r.role_id = e.role_id
    ORDER BY e.first_name ASC, e.last_name ASC
");
$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Employee Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        h2, h3 { margin-bottom: 10px; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .row { margin-bottom: 12px; }
        label { display: inline-block; min-width: 120px; }
        input, select { padding: 8px; width: 260px; max-width: 100%; }
        button { padding: 8px 14px; cursor: pointer; }
        .btn-primary { background: #1f4e79; color: #fff; border: 0; }
        .btn-danger { background: #b22222; color: #fff; border: 0; }
        .btn-neutral { background: #555; color: #fff; border: 0; }
        .notice { padding: 10px 12px; border-radius: 6px; margin-bottom: 10px; }
        .success { background: #e8f6ec; color: #1c5c2a; border: 1px solid #b7e2c2; }
        .error { background: #fdeaea; color: #7b1f1f; border: 1px solid #f5bdbd; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .tag-active { color: #136f2d; font-weight: bold; }
        .tag-inactive { color: #a33333; font-weight: bold; }
        .inline-form { display: inline; }
        .muted { color: #555; font-size: 13px; }
    </style>
</head>
<body>
    <h2>Admin Employee Management</h2>
    <p class="muted">Create employees/admins and activate/deactivate accounts without writing SQL manually.</p>

    <?php foreach ($messages as $message): ?>
        <div class="notice success"><?php echo htmlspecialchars($message); ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="notice error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="card">
        <h3>Add Employee</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create_employee">

            <div class="row">
                <label for="first_name">First Name</label>
                <input id="first_name" name="first_name" required>
            </div>

            <div class="row">
                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" required>
            </div>

            <div class="row">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required>
            </div>

            <div class="row">
                <label for="role_id">Role</label>
                <select id="role_id" name="role_id" required>
                    <option value="">Select role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo (int) $role['role_id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <label for="pin">PIN (optional)</label>
                <input id="pin" name="pin" pattern="\d{5}" maxlength="5" placeholder="Auto-generated if blank">
            </div>

            <?php if ($passwordColumnExists): ?>
                <div class="row">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
            <?php endif; ?>

            <button class="btn-primary" type="submit">Create Employee</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Employees</h3>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>PIN</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                        <td><?php echo htmlspecialchars($employee['role_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo htmlspecialchars($employee['pin']); ?></td>
                        <td>
                            <?php if ((int) $employee['is_active'] === 1): ?>
                                <span class="tag-active">Active</span>
                            <?php else: ?>
                                <span class="tag-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $employee['is_active'] === 1): ?>
                                <form class="inline-form" method="POST">
                                    <input type="hidden" name="action" value="deactivate_employee">
                                    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['employee_id']; ?>">
                                    <button class="btn-danger" type="submit">Deactivate</button>
                                </form>
                            <?php else: ?>
                                <form class="inline-form" method="POST">
                                    <input type="hidden" name="action" value="activate_employee">
                                    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['employee_id']; ?>">
                                    <button class="btn-primary" type="submit">Activate</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="6">No employees found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="manager_dashboard.php"><button class="btn-neutral" type="button">Back to Dashboard</button></a>
</body>
</html>
