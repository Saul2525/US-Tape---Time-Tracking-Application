<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class UpdateShiftTest extends BaseWebTest
{
    // --------------------------------------------------
    // Update Shift
    // --------------------------------------------------

    public function testManagerCanUpdateShiftAndAuditChanges(): void
    {
        // Create a manager account that will be used as the
        // person making the shift adjustment.
        // Role 2 represents a manager.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        // Create the employee whose shift will be edited.
        // Role 1 represents a regular employee.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an existing completed shift.
        // These values represent the original state before
        // the manager makes an adjustment.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00',
            0,
            0
        );

        // Retrieve the work-time ID so the POST request can
        // identify which shift should be updated.
        $stmt = $this->pdo->prepare("
            SELECT work_time_id
            FROM WORK_TIMES
            WHERE employee_id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);

        $workTimeId = (int) $stmt->fetchColumn();

        // Create a session representing the manager who is
        // performing the adjustment.
        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Submit the new shift information.
        // The manager changes both times, approves the shift,
        // and adds a note explaining the adjustment.
        $response = $this->post(
            $this->baseUrl . '/update_shift.php',
            [
                'employee_id' => $employeeId,
                'save_id' => $workTimeId,
                'clock_in' => [
                    $workTimeId => '2026-09-28 08:30:00'
                ],
                'clock_out' => [
                    $workTimeId => '2026-09-28 17:30:00'
                ],
                'approved' => [
                    $workTimeId => '1'
                ],
                'note' => [
                    $workTimeId => 'Adjusted for corrected work hours'
                ]
            ],
            $sessionId
        );

        // The update script should redirect back to the
        // employee's shift page after saving the changes.
        $this->assertSame(302, $response['status']);

        // Verify that the shift itself was updated in WORK_TIMES.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE work_time_id = ?
        ");
        $stmt->execute([$workTimeId]);

        $workTime = $stmt->fetch();

        // The shift should still exist after the update.
        $this->assertNotFalse($workTime);

        // Verify the new clock-in and clock-out times.
        $this->assertSame(
            '2026-09-28 08:30:00',
            $workTime['clock_in_time']
        );

        $this->assertSame(
            '2026-09-28 17:30:00',
            $workTime['clock_out_time']
        );

        // The manager checked the approval checkbox, so the
        // shift should now be approved.
        $this->assertSame('1', (string) $workTime['approved']);

        // Updating a shift should mark it as manually edited.
        $this->assertSame('1', (string) $workTime['is_edited']);

        // Verify that the manager's note was saved.
        $this->assertSame(
            'Adjusted for corrected work hours',
            $workTime['manager_note']
        );

        // --------------------------------------------------
        // Audit Log
        // --------------------------------------------------

        // The update should also create an audit record so that
        // the original and updated values can be reviewed later.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM AUDIT_LOG
            WHERE work_time_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workTimeId]);

        $audit = $stmt->fetch();

        // An audit record must exist for every successful
        // manual shift adjustment.
        $this->assertNotFalse($audit);

        // Verify that the audit record identifies the correct
        // shift, employee, and manager who made the change.
        $this->assertSame(
            (string) $workTimeId,
            (string) $audit['work_time_id']
        );

        $this->assertSame(
            (string) $employeeId,
            (string) $audit['employee_id']
        );

        $this->assertSame(
            (string) $managerId,
            (string) $audit['changed_by']
        );

        // Decode the JSON values so we can verify exactly what
        // the audit log recorded before and after the update.
        $oldValues = json_decode(
            $audit['old_values'],
            true
        );

        $newValues = json_decode(
            $audit['new_values'],
            true
        );

        // The old values should match the original shift.
        $this->assertSame(
            '2026-09-28 09:00:00',
            $oldValues['clock_in']
        );

        $this->assertSame(
            '2026-09-28 17:00:00',
            $oldValues['clock_out']
        );

        $this->assertSame(
            0,
            (int) $oldValues['approved']
        );

        $this->assertNull($oldValues['manager_note']);

        // The new values should match the values submitted
        // by the manager.
        $this->assertSame(
            '2026-09-28 08:30:00',
            $newValues['clock_in']
        );

        $this->assertSame(
            '2026-09-28 17:30:00',
            $newValues['clock_out']
        );

        $this->assertSame(
            1,
            (int) $newValues['approved']
        );

        $this->assertSame(
            'Adjusted for corrected work hours',
            $newValues['manager_note']
        );

        // Verify that the audit record identifies the operation
        // as a manual adjustment made by a manager.
        $this->assertSame(
            'Manual adjustment by manager',
            $audit['change_reason']
        );

        // Clean up the test session after the request.
        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Empty Manager Note
    // --------------------------------------------------

    public function testEmptyManagerNoteIsStoredAsNull(): void
    {
        // Create the manager who will perform the adjustment.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        // Create the employee whose shift will be edited.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an existing completed shift.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Retrieve the work-time ID needed by update_shift.php.
        $stmt = $this->pdo->prepare("
            SELECT work_time_id
            FROM WORK_TIMES
            WHERE employee_id = ?
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);

        $workTimeId = (int) $stmt->fetchColumn();

        // Create a session for the manager.
        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Submit the same shift with an empty manager note.
        // update_shift.php should convert an empty note to NULL.
        $this->post(
            $this->baseUrl . '/update_shift.php',
            [
                'employee_id' => $employeeId,
                'save_id' => $workTimeId,
                'clock_in' => [
                    $workTimeId => '2026-09-28 09:00:00'
                ],
                'clock_out' => [
                    $workTimeId => '2026-09-28 17:00:00'
                ],
                'note' => [
                    $workTimeId => ''
                ]
            ],
            $sessionId
        );

        // Retrieve the updated shift from the database.
        $stmt = $this->pdo->prepare("
            SELECT manager_note
            FROM WORK_TIMES
            WHERE work_time_id = ?
        ");
        $stmt->execute([$workTimeId]);

        $note = $stmt->fetchColumn();

        // An empty note should be stored as NULL rather than
        // an empty string.
        $this->assertNull($note);

        // Clean up the test session.
        $this->destroySession($sessionId);
    }
}
