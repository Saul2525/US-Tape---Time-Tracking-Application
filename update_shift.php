<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'];
    $save_id = $_POST['save_id'];

    // Get old values for audit
    $stmt = $pdo->prepare("SELECT * FROM WORK_TIMES WHERE work_time_id = ?");
    $stmt->execute([$save_id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);

    // New values
    $clock_in  = $_POST['clock_in'][$save_id];
    $clock_out = $_POST['clock_out'][$save_id];
    $approved  = isset($_POST['approved'][$save_id]) ? 1 : 0;
    $note      = trim($_POST['note'][$save_id] ?? '');
    $note      = $note !== '' ? $note : null;

    // Update shift
    $stmt = $pdo->prepare("
        UPDATE WORK_TIMES
        SET clock_in_time = ?, clock_out_time = ?, approved = ?, manager_note = ?, is_edited = 1
        WHERE work_time_id = ?
    ");
    $stmt->execute([$clock_in, $clock_out, $approved, $note, $save_id]);

    // Insert audit log
    $stmt = $pdo->prepare("
        INSERT INTO AUDIT_LOG
        (work_time_id, employee_id, changed_by, old_values, new_values, change_reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $save_id,
        $employee_id,
        $_SESSION['user_id'],
        json_encode(['clock_in'=>$old['clock_in_time'],'clock_out'=>$old['clock_out_time'],'approved'=>$old['approved'],'manager_note'=>$old['manager_note']]),
        json_encode(['clock_in'=>$clock_in,'clock_out'=>$clock_out,'approved'=>$approved,'manager_note'=>$note]),
        'Manual adjustment by manager'
    ]);

    header("Location: employee_shifts.php?employee_id=$employee_id");
    exit;
}