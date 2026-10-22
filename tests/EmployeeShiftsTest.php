<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class EmployeeShiftsTest extends BaseWebTest
{
    // --------------------------------------------------
    // Employee Shift Display
    // --------------------------------------------------

    public function testEmployeeShiftsPageDisplaysEmployeeAndShifts(): void
    {
        // Create the employee whose shifts will be displayed.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create a completed shift for the employee.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00',
            1,
            0
        );

        // Request the employee's shift page for the date
        // containing the test shift.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ]
        );

        // The page should load successfully.
        $this->assertSame(200, $response['status']);

        // The page should identify the employee whose shifts
        // are being displayed.
        $this->assertStringContainsString(
            'John Doe - Shifts',
            $response['body']
        );

        // The shift's times should appear on the page.
        $this->assertStringContainsString(
            '2026-09-28 09:00',
            $response['body']
        );

        $this->assertStringContainsString(
            '2026-09-28 17:00',
            $response['body']
        );

        // The approved shift should be represented by a checked
        // approval checkbox.
        $this->assertStringContainsString(
            'checked',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Date Range Filtering
    // --------------------------------------------------

    public function testOnlyShiftsInsideDateRangeAreDisplayed(): void
    {
        // Create the employee whose shifts will be filtered.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create one shift inside the requested date range.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Create another shift outside the requested date range.
        $this->createWorkTime(
            $employeeId,
            '2026-09-29 09:00:00',
            '2026-09-29 17:00:00'
        );

        // Request only September 28.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-28'
            ]
        );

        // The page should load successfully.
        $this->assertSame(200, $response['status']);

        // The shift inside the selected range should be shown.
        $this->assertStringContainsString(
            '2026-09-28 09:00',
            $response['body']
        );

        // The shift outside the selected range should not be shown.
        $this->assertStringNotContainsString(
            '2026-09-29 09:00',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Total Hours
    // --------------------------------------------------

    public function testTotalHoursAreCalculatedForSelectedRange(): void
    {
        // Create the employee whose total hours will be calculated.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an 8-hour shift.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Create a second 4-hour shift.
        $this->createWorkTime(
            $employeeId,
            '2026-09-29 09:00:00',
            '2026-09-29 13:00:00'
        );

        // Request both days so both shifts are included.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-28',
                'to' => '2026-09-29'
            ]
        );

        // The page should load successfully.
        $this->assertSame(200, $response['status']);

        // 8 hours + 4 hours should produce a 12-hour total.
        $this->assertStringContainsString(
            '12',
            $response['body']
        );

        // The page should identify that the value is the
        // total for the selected range.
        $this->assertStringContainsString(
            'Total Hours in Selected Range:',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Open Shift
    // --------------------------------------------------

    public function testOpenShiftIsIncludedInTotalHours(): void
    {
        // Create the employee with an open shift.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an open shift by leaving clock_out_time NULL.
        // employee_shifts.php should calculate its duration using
        // the current UTC time.
        $this->createWorkTime(
            $employeeId,
            gmdate('Y-m-d H:i:s', time() - 3600),
            null
        );

        // Request today's date range.
        $today = gmdate('Y-m-d');

        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => $today,
                'to' => $today
            ]
        );

        // The page should load successfully.
        $this->assertSame(200, $response['status']);

        // The open shift should be displayed even though it has
        // no clock-out time yet.
        $this->assertStringContainsString(
            'John Doe - Shifts',
            $response['body']
        );

        // The calculated total should be greater than zero.
        // Because the application calculates the duration using
        // the current UTC time, we avoid asserting an exact value.
        $this->assertDoesNotMatchRegularExpression(
            '/Total Hours in Selected Range:\s*<span[^>]*>0(?:\.0+)?<\/span>/',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Reversed Date Range
    // --------------------------------------------------

    public function testReversedDateRangeIsAutomaticallyCorrected(): void
    {
        // Create an employee and a shift on the earlier date.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $this->createWorkTime(
            $employeeId,
            '2026-09-28 09:00:00',
            '2026-09-28 17:00:00'
        );

        // Intentionally provide the dates in reverse order.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => '2026-09-29',
                'to' => '2026-09-28'
            ]
        );

        // The application should swap the dates instead of
        // producing an invalid or empty range.
        $this->assertSame(200, $response['status']);

        // The September 28 shift should therefore still appear.
        $this->assertStringContainsString(
            '2026-09-28 09:00',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Invalid Date Input
    // --------------------------------------------------

    public function testInvalidDatesFallBackToDefaultRange(): void
    {
        // Create an employee.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // The invalid dates should be replaced by the page's
        // normal default date range rather than being used directly
        // in the database query.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => $employeeId,
                'from' => 'not-a-date',
                'to' => 'also-not-a-date'
            ]
        );

        // The page should still load instead of failing because
        // of the invalid date parameters.
        $this->assertSame(200, $response['status']);

        // The employee information should still be displayed,
        // confirming that the request continued normally after
        // the invalid dates were replaced.
        $this->assertStringContainsString(
            'John Doe - Shifts',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Missing Employee
    // --------------------------------------------------

    public function testNonexistentEmployeeIsRejected(): void
    {
        // Use an employee ID that does not exist in the test database.
        $response = $this->get(
            $this->baseUrl . '/employee_shifts.php',
            [
                'employee_id' => 999999
            ]
        );

        // employee_shifts.php should reject the request rather than
        // displaying an empty employee page.
        $this->assertSame(200, $response['status']);

        $this->assertStringContainsString(
            'Employee not found.',
            $response['body']
        );
    }
}
