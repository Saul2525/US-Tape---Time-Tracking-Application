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
        <button class="export-button" type="submit" form="exportForm">Export Selected to Excel</button>
    </div>
    <p style="margin: 6px 0 0 0; font-size: 13px; color: #4b5563;">
        Check specific employees below to export just them — your checks are kept even if you search for someone else first. Leave everyone unchecked to export the full filtered list.
    </p>
    <div style="margin: 10px 0; display:flex; align-items:center; gap:12px;">
        <span id="selectionStatus" style="font-size:13px; color:#1f4e79; font-weight:600;"></span>
        <button type="button" id="clearSelection" style="padding:4px 10px; font-size:12px; cursor:pointer;">Clear Selection</button>
    </div>

    <form method="GET" style="margin-top:15px;">
        From: <input type="date" name="from" value="<?php echo $from; ?>">
        To: <input type="date" name="to" value="<?php echo $to; ?>">
        Search Employee: <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Filter</button>
    </form>

    <form method="GET" action="export_shifts.php" id="exportForm">
        <input type="hidden" name="from" value="<?php echo htmlspecialchars($from); ?>">
        <input type="hidden" name="to" value="<?php echo htmlspecialchars($to); ?>">
        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAll" title="Select all"></th>
                    <th>Employee</th>
                    <th>Email</th>
                    <th>View Shifts</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><input type="checkbox" class="emp-checkbox" name="employee_ids[]" value="<?php echo $emp['employee_id']; ?>"></td>
                        <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                        <td>
                            <a href="employee_shifts.php?employee_id=<?php echo $emp['employee_id']; ?>">
                                <button type="button">View Shifts</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)) echo "<tr><td colspan='4'>No employees found</td></tr>"; ?>
            </tbody>
        </table>
    </form>

    <script>
    (function () {
        var STORAGE_KEY = 'ustape_export_selected_employee_ids';
        var form = document.getElementById('exportForm');
        var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.emp-checkbox'));
        var selectAll = document.getElementById('selectAll');
        var statusEl = document.getElementById('selectionStatus');
        var visibleIds = checkboxes.map(function (cb) { return cb.value; });

        function loadSelected() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                return raw ? JSON.parse(raw) : [];
            } catch (e) {
                return [];
            }
        }

        function saveSelected() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(selected));
            } catch (e) {
                // localStorage unavailable (private browsing, etc.) - selection just won't persist across searches.
            }
        }

        var selected = loadSelected();

        function setSelected(id, isChecked) {
            var idx = selected.indexOf(id);
            if (isChecked && idx === -1) {
                selected.push(id);
            } else if (!isChecked && idx !== -1) {
                selected.splice(idx, 1);
            }
        }

        function refreshOffscreenHiddenInputs() {
            form.querySelectorAll('input[data-offscreen-selection]').forEach(function (el) {
                el.remove();
            });
            selected.forEach(function (id) {
                if (visibleIds.indexOf(id) === -1) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'employee_ids[]';
                    hidden.value = id;
                    hidden.setAttribute('data-offscreen-selection', '1');
                    form.appendChild(hidden);
                }
            });
        }

        function refreshStatus() {
            statusEl.textContent = selected.length
                ? selected.length + ' employee(s) selected for export (kept across searches).'
                : 'No employees selected yet — export will include everyone in the filtered list.';
        }

        function persist() {
            saveSelected();
            refreshOffscreenHiddenInputs();
            refreshStatus();
        }

        // Restore checked state for anyone currently visible who was selected earlier.
        checkboxes.forEach(function (cb) {
            if (selected.indexOf(cb.value) !== -1) {
                cb.checked = true;
            }
            cb.addEventListener('change', function () {
                setSelected(cb.value, cb.checked);
                persist();
            });
        });

        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (cb) {
                cb.checked = selectAll.checked;
                setSelected(cb.value, selectAll.checked);
            });
            persist();
        });

        document.getElementById('clearSelection').addEventListener('click', function () {
            selected = [];
            checkboxes.forEach(function (cb) { cb.checked = false; });
            selectAll.checked = false;
            persist();
        });

        persist();
    })();
    </script>
</body>
</html>