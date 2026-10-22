<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class ExportShiftsTest extends BaseWebTest
{
    // --------------------------------------------------
    // Access Control
    // --------------------------------------------------

    public function testManagerCanExportShifts(): void
    {
        // Create a manager account.
        // Role 2 has permission to edit other employees.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        // Create an employee with a completed shift.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00',
            1,
            0
        );

        // Create a session representing the manager.
        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Request the employee's shift export.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // The manager should be allowed to access the export.
        $this->assertSame(200, $response['status']);

        // The CSV should contain the expected header.
        $this->assertStringContainsString(
            'Employee,Email,"Clock In","Clock Out",Hours,Approved,Edited,Note',
            $response['body']
        );

        // The employee's shift should appear in the export.
        $this->assertStringContainsString(
            'John Doe',
            $response['body']
        );

        $this->assertStringContainsString(
            'john@example.com',
            $response['body']
        );

        // The completed shift should show its calculated
        // eight-hour duration and approval status.
        $this->assertStringContainsString(
            '8',
            $response['body']
        );

        $this->assertStringContainsString(
            'Yes',
            $response['body']
        );

        // Clean up the manager session.
        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Access Denied
    // --------------------------------------------------

    public function testEmployeeCannotExportOtherEmployeesShifts(): void
    {
        // Create a regular employee.
        // Role 1 does not have can_edit_others permission.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create another employee whose shifts would otherwise
        // be available for export.
        $otherEmployeeId = $this->createEmployee(
            'Jane',
            'Smith',
            'jane@example.com',
            1
        );

        $this->createWorkTime(
            $otherEmployeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Create a session for the regular employee.
        $sessionId = $this->createUserSession(
            $employeeId,
            1
        );

        // Attempt to export another employee's shift data.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_id' => $otherEmployeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // The export should be denied.
        $this->assertStringContainsString(
            'Access denied.',
            $response['body']
        );

        // Clean up the session.
        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Date Filtering
    // --------------------------------------------------

    public function testExportOnlyContainsShiftsInsideDateRange(): void
    {
        // Create a manager who can access exports.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create a shift inside the selected range.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Create another shift outside the selected range.
        $this->createWorkTime(
            $employeeId,
            '2026-09-29 09:00:00',
            '2026-09-29 17:00:00'
        );

        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Export only September 28.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // The September 28 shift should be included.
        $this->assertStringContainsString(
            '2026-09-28 09:00',
            $response['body']
        );

        // The September 29 shift should not be included.
        $this->assertStringNotContainsString(
            '2026-09-29 09:00',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Filtering
    // --------------------------------------------------

    public function testExportOnlyContainsSelectedEmployee(): void
    {
        // Create a manager with export permission.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        // Create two employees.
        $employeeOneId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $employeeTwoId = $this->createEmployee(
            'Alice',
            'Smith',
            'alice@example.com',
            1
        );

        // Give both employees a shift on the same date.
        $this->createWorkTime(
            $employeeOneId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        $this->createWorkTime(
            $employeeTwoId,
            '2026-09-28 10:00:00',
            '2026-09-28 18:00:00'
        );

        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Request an export specifically for the first employee.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_id' => $employeeOneId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // The selected employee should be included.
        $this->assertStringContainsString(
            'John Doe',
            $response['body']
        );

        // The other employee should not appear in the export.
        $this->assertStringNotContainsString(
            'Alice Smith',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Totals
    // --------------------------------------------------

    public function testExportIncludesSeparateTotalForEachEmployee(): void
    {
        // Create a manager with export permission.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        // Create two employees.
        $employeeOneId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $employeeTwoId = $this->createEmployee(
            'Alice',
            'Smith',
            'alice@example.com',
            1
        );

        // John works 8 hours.
        $this->createWorkTime(
            $employeeOneId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Alice works 4 hours.
        $this->createWorkTime(
            $employeeTwoId,
            '2026-09-28 09:00:00',
            '2026-09-28 13:00:00'
        );

        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Export both employees using the employee_ids parameter.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_ids' => [
                    $employeeOneId,
                    $employeeTwoId
                ],
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // Both employees should appear in the export.
        $this->assertStringContainsString(
            'John Doe',
            $response['body']
        );

        $this->assertStringContainsString(
            'Alice Smith',
            $response['body']
        );

        // Each employee should receive a separate total row.
        $this->assertStringContainsString(
            'Total Hours (John Doe)',
            $response['body']
        );

        $this->assertStringContainsString(
            'Total Hours (Alice Smith)',
            $response['body']
        );

        // John should have an 8-hour total and Alice a 4-hour total.
        $this->assertStringContainsString(
            ',,,"Total Hours (John Doe)",8,,,',
            $response['body']
        );

        $this->assertStringContainsString(
            ',,,"Total Hours (Alice Smith)",4,,,',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Open Shift
    // --------------------------------------------------

    public function testOpenShiftIsExportedAsOpen(): void
    {
        // Create a manager with export permission.
        $managerId = $this->createEmployee(
            'Jane',
            'Manager',
            'jane.manager@example.com',
            2
        );

        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create a shift that has not been clocked out yet.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            null
        );

        $sessionId = $this->createUserSession(
            $managerId,
            2
        );

        // Export the employee's shift.
        $response = $this->get(
            $this->baseUrl . '/export_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ],
            $sessionId
        );

        // An open shift should display "Open" instead of a
        // clock-out timestamp.
        $this->assertStringContainsString(
            'Open',
            $response['body']
        );

        // Open shifts should not receive a calculated hours value.
        // The export code intentionally leaves Hours blank.
        $this->assertStringContainsString(
            ',Open,,',
            $response['body']
        );

        $this->destroySession($sessionId);
    }
}
