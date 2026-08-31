<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class ClockHandlerTest extends BaseWebTest
{
    /**
     * Tests that an active employee with a valid PIN
     * can successfully clock in.
     */
    public function testClockIn(): void
    {
        // Create an employee with a known PIN.
        $this->pdo->prepare("
            INSERT INTO EMPLOYEES
            (first_name, last_name, email, pin, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            'John',
            'Doe',
            'john@test.com',
            '12345',
            1,
            1
        ]);

        // Send a clock-in request with the employee's PIN.
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345',
                'lat' => '40.7128',
                'lng' => '-74.0060'
            ]
        );

        // The request should be successful.
        $this->assertEquals(200, $response['status']);

        // The response should indicate that the employee clocked in.
        $this->assertStringContainsString(
            'Clocked IN successfully.',
            $response['body']
        );

        // Verify that a WORK_TIMES record was created.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = (
                SELECT employee_id
                FROM EMPLOYEES
                WHERE email = ?
            )
        ");

        $stmt->execute(['john@test.com']);

        $workTime = $stmt->fetch();

        $this->assertNotFalse($workTime);

        // A clock-in should have a timestamp.
        $this->assertNotEmpty($workTime['clock_in_time']);

        // A newly clocked-in employee should not have a clock-out time.
        $this->assertNull($workTime['clock_out_time']);

        // Verify that the location was recorded.
        $this->assertEqualsWithDelta(
            40.7128,
            (float) $workTime['clock_in_lat'],
            0.000001
        );

        $this->assertEqualsWithDelta(
            -74.0060,
            (float) $workTime['clock_in_lng'],
            0.000001
        );
    }

    /**
     * Tests that an employee with an open shift
     * can successfully clock out.
     */
    public function testClockOut(): void
    {
        // Create an employee with a known PIN.
        $this->pdo->prepare("
            INSERT INTO EMPLOYEES
            (first_name, last_name, email, pin, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            'John',
            'Doe',
            'john@test.com',
            '12345',
            1,
            1
        ]);

        // Create an open shift for the employee.
        $employee = $this->getEmployeeByEmail('john@test.com');

        $this->pdo->prepare("
            INSERT INTO WORK_TIMES
            (employee_id, clock_in_time)
            VALUES (?, UTC_TIMESTAMP())
        ")->execute([
            $employee['employee_id']
        ]);

        // Send a clock request using the employee's PIN.
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345',
                'lat' => '40.7128',
                'lng' => '-74.0060'
            ]
        );

        // The request should be successful.
        $this->assertEquals(200, $response['status']);

        // The response should indicate that the employee clocked out.
        $this->assertStringContainsString(
            'Clocked OUT successfully.',
            $response['body']
        );

        // Retrieve the employee's work-time record.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([
            $employee['employee_id']
        ]);

        $workTime = $stmt->fetch();

        $this->assertNotFalse($workTime);

        // Verify that the shift now has a clock-out timestamp.
        $this->assertNotEmpty($workTime['clock_out_time']);

        // Verify that the clock-out location was recorded.
        $this->assertEqualsWithDelta(
            40.7128,
            (float) $workTime['clock_out_lat'],
            0.000001
        );

        $this->assertEqualsWithDelta(
            -74.0060,
            (float) $workTime['clock_out_lng'],
            0.000001
        );
    }

    /**
     * Tests that an invalid PIN format is rejected
     * before attempting to look up an employee.
     */
    public function testInvalidPinFormat(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '123'
            ]
        );

        // The request should still return an HTTP response.
        $this->assertEquals(200, $response['status']);

        // The application should report the invalid PIN format.
        $this->assertStringContainsString(
            'Invalid PIN format.',
            $response['body']
        );
    }

    /**
     * Tests that a PIN containing non-numeric characters
     * is rejected.
     */
    public function testNonNumericPin(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12abc'
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Invalid PIN format.',
            $response['body']
        );
    }

    /**
     * Tests that a correctly formatted PIN which does not
     * belong to an employee is rejected.
     */
    public function testUnknownPin(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '99999'
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Invalid PIN.',
            $response['body']
        );
    }

    /**
     * Tests that an inactive employee cannot use their PIN
     * to clock in or out.
     */
    public function testInactiveEmployeeCannotClockIn(): void
    {
        // Create an inactive employee.
        $this->pdo->prepare("
            INSERT INTO EMPLOYEES
            (first_name, last_name, email, pin, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            'John',
            'Doe',
            'john@test.com',
            '12345',
            1,
            0
        ]);

        // Attempt to clock in using the inactive employee's PIN.
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        $this->assertEquals(200, $response['status']);

        // The inactive employee should not be found.
        $this->assertStringContainsString(
            'Invalid PIN.',
            $response['body']
        );

        // Verify that no work-time record was created.
        $stmt = $this->pdo->query("
            SELECT COUNT(*)
            FROM WORK_TIMES
        ");

        $this->assertEquals(0, $stmt->fetchColumn());
    }

    /**
     * Tests that location information is optional.
     *
     * The clock handler allows latitude and longitude
     * to be omitted from the request.
     */
    public function testClockInWithoutLocation(): void
    {
        // Create an active employee.
        $this->pdo->prepare("
            INSERT INTO EMPLOYEES
            (first_name, last_name, email, pin, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            'John',
            'Doe',
            'john@test.com',
            '12345',
            1,
            1
        ]);

        // Clock in without providing location information.
        $response = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Clocked IN successfully.',
            $response['body']
        );

        // Verify that the shift was still created.
        $user = $this->getEmployeeByEmail('john@test.com');

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([
            $user['employee_id']
        ]);

        $workTime = $stmt->fetch();

        $this->assertNotFalse($workTime);

        // Location should be NULL when it was not supplied.
        $this->assertNull($workTime['clock_in_lat']);
        $this->assertNull($workTime['clock_in_lng']);
    }

    /**
     * Tests that the same employee can clock in and then
     * clock out using two separate HTTP requests.
     */
    public function testClockInThenClockOut(): void
    {
        // Create an active employee.
        $this->pdo->prepare("
            INSERT INTO EMPLOYEES
            (first_name, last_name, email, pin, role_id, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            'John',
            'Doe',
            'john@test.com',
            '12345',
            1,
            1
        ]);

        // First request should clock the employee in.
        $clockInResponse = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        $this->assertEquals(200, $clockInResponse['status']);

        $this->assertStringContainsString(
            'Clocked IN successfully.',
            $clockInResponse['body']
        );

        // Second request should detect the open shift
        // and clock the employee out.
        $clockOutResponse = $this->post(
            'http://127.0.0.1:8000/clock_handler.php',
            [
                'pin' => '12345'
            ]
        );

        $this->assertEquals(200, $clockOutResponse['status']);

        $this->assertStringContainsString(
            'Clocked OUT successfully.',
            $clockOutResponse['body']
        );

        // Verify that only one work-time record exists.
        $user = $this->getEmployeeByEmail('john@test.com');

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([
            $user['employee_id']
        ]);

        $this->assertEquals(1, $stmt->fetchColumn());

        // Verify that the existing shift was closed.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM WORK_TIMES
            WHERE employee_id = ?
        ");

        $stmt->execute([
            $user['employee_id']
        ]);

        $workTime = $stmt->fetch();

        $this->assertNotFalse($workTime);
        $this->assertNotEmpty($workTime['clock_in_time']);
        $this->assertNotEmpty($workTime['clock_out_time']);
    }
}
