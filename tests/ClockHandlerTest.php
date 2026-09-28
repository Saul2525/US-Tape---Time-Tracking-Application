<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class ClockHandlerTest extends BaseWebTest
{
    // --------------------------------------------------
    // Clock In
    // --------------------------------------------------

    public function testEmployeeCanClockIn(): void
    {
        // Create an active employee with the PIN that will be
        // submitted to the clock handler.
        // Role 1 represents a regular employee.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Send a POST request using the employee's valid
        // five-digit PIN and a test location.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '12345',
                'lat' => '40.6075',
                'lng' => '-75.3785'
            ]
        );

        // The request should complete successfully and the
        // page should report that the employee clocked in.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Clocked IN successfully.',
            $response['body']
        );

        // Retrieve the employee's work time record so we can
        // verify that the clock-in was actually saved.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([$employeeId]);

        $workTime = $stmt->fetch();

        // A successful clock-in should create a work-time record
        // with a clock-in time and no clock-out time yet.
        $this->assertNotFalse($workTime);
        $this->assertNotNull($workTime['clock_in_time']);
        $this->assertNull($workTime['clock_out_time']);

        // Verify that the latitude and longitude submitted with
        // the request were stored with the clock-in.
        $this->assertSame('40.607500', $workTime['clock_in_lat']);
        $this->assertSame('-75.378500', $workTime['clock_in_lng']);

        // Because the test server is running locally, the request
        // should record 127.0.0.1 as the client's IP address.
        $this->assertSame('127.0.0.1', $workTime['clock_in_ip']);
    }

    // --------------------------------------------------
    // Clock Out
    // --------------------------------------------------

    public function testEmployeeCanClockOut(): void
    {
        // Create an active employee with the PIN used by the test.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an existing open shift for the employee.
        // A NULL clock_out_time represents a shift that is
        // currently open.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 13:00:00',
            null
        );

        // Send another request with the employee's valid PIN.
        // Because an open shift already exists, clock_handler.php
        // should clock the employee out instead of creating
        // another shift.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '12345',
                'lat' => '40.6100',
                'lng' => '-75.3800'
            ]
        );

        // The request should complete successfully and report
        // that the employee clocked out.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Clocked OUT successfully.',
            $response['body']
        );

        // Retrieve the employee's work-time record to verify
        // that the existing shift was updated.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([$employeeId]);

        $workTime = $stmt->fetch();

        // The existing shift should now contain a clock-out time.
        $this->assertNotFalse($workTime);
        $this->assertNotNull($workTime['clock_out_time']);

        // Verify that the latitude and longitude submitted during
        // clock-out were saved to the correct columns.
        $this->assertSame('40.610000', $workTime['clock_out_lat']);
        $this->assertSame('-75.380000', $workTime['clock_out_lng']);

        // Verify that the server recorded the client's IP address.
        $this->assertSame('127.0.0.1', $workTime['clock_out_ip']);
    }

    // --------------------------------------------------
    // Invalid PIN
    // --------------------------------------------------

    public function testInvalidPinFormatIsRejected(): void
    {
        // Submit a PIN containing only four digits.
        // clock_handler.php requires exactly five digits.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '1234'
            ]
        );

        // The request itself completes, but the application
        // should reject the PIN because its format is invalid.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid PIN format.',
            $response['body']
        );
    }

    public function testNonexistentPinIsRejected(): void
    {
        // Submit a properly formatted five-digit PIN that does
        // not belong to any employee in the test database.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '99999'
            ]
        );

        // The application should reject the PIN because no
        // active employee matches it.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid PIN.',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Inactive Employee
    // --------------------------------------------------

    public function testInactiveEmployeeCannotClockIn(): void
    {
        // Create an employee who initially has an active account.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Deactivate the employee before attempting to use
        // their PIN. clock_handler.php only searches for
        // employees where is_active = 1.
        $stmt = $this->pdo->prepare("
            UPDATE EMPLOYEES
            SET is_active = 0
            WHERE employee_id = ?
        ");

        $stmt->execute([$employeeId]);

        // Attempt to clock in using the inactive employee's PIN.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        // The application should treat the PIN as invalid because
        // inactive employees cannot use the clock.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid PIN.',
            $response['body']
        );

        // No work-time record should have been created because
        // the inactive employee was not allowed to clock in.
        $count = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM WORK_TIMES")
            ->fetchColumn();

        $this->assertSame(0, $count);
    }

    // --------------------------------------------------
    // Existing Open Shift
    // --------------------------------------------------

    public function testClockOutClosesExistingOpenShift(): void
    {
        // Create an active employee with the PIN used by the test.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an existing open shift.
        // The NULL clock_out_time indicates that the employee
        // has already clocked in but has not clocked out.
        $this->createWorkTime(
            $employeeId,
            '2026-09-28 13:00:00',
            null
        );

        // Record the number of work-time records before sending
        // the clock-out request.
        $beforeCount = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM WORK_TIMES")
            ->fetchColumn();

        // Submit the employee's valid PIN.
        // Because an open shift exists, the application should
        // close that shift rather than create a new one.
        $response = $this->post(
            $this->baseUrl . '/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        // The request should complete successfully.
        $this->assertSame(200, $response['status']);

        // Verify that no additional WORK_TIMES record was created.
        $afterCount = (int) $this->pdo
            ->query("SELECT COUNT(*) FROM WORK_TIMES")
            ->fetchColumn();

        $this->assertSame($beforeCount, $afterCount);

        // Retrieve the existing shift and verify that it now
        // contains a clock-out timestamp.
        $stmt = $this->pdo->prepare("
            SELECT clock_out_time
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([$employeeId]);

        $clockOut = $stmt->fetchColumn();

        $this->assertNotFalse($clockOut);
        $this->assertNotNull($clockOut);
    }
}
