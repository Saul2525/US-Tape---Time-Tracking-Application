<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

/**
 * Tests the employee creation functionality provided by create_user.php.
 *
 * These tests send HTTP POST requests to the application and verify both
 * the HTTP response and the resulting database state.
 */
class CreateUserTest extends BaseWebTest
{
    /**
     * Tests that an employee can be successfully created with valid input.
     *
     * Verifies:
     * - The request returns HTTP 200.
     * - A success message is displayed.
     * - The employee is stored with the correct information.
     * - A unique five-digit PIN is generated.
     * - The employee is active by default.
     */
    public function testCreateUserSuccess(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john@test.com',
                'role_id'    => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Employee Created Successfully',
            $response['body']
        );

        $user = $this->getEmployeeByEmail('john@test.com');

        $this->assertNotFalse($user);
        $this->assertEquals('John', $user['first_name']);
        $this->assertEquals('Doe', $user['last_name']);
        $this->assertEquals('john@test.com', $user['email']);
        $this->assertEquals(1, $user['role_id']);

        // Verify that a five-digit PIN was generated.
        $this->assertNotEmpty($user['pin']);
        $this->assertEquals(5, strlen($user['pin']));
        $this->assertMatchesRegularExpression('/^\d{5}$/', $user['pin']);

        // Employees should be active when initially created.
        $this->assertEquals(1, $user['is_active']);
    }

    /**
     * Tests that an invalid email address is rejected.
     *
     * Verifies that:
     * - The request does not create an employee.
     * - The expected validation message is returned.
     */
    public function testInvalidEmail(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'not-an-email',
                'role_id'    => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Invalid email format.',
            $response['body']
        );

        // Confirm that the invalid employee was not inserted.
        $user = $this->getEmployeeByEmail('not-an-email');
        $this->assertFalse($user);
    }

    /**
     * Tests that an employee cannot be created without a first name.
     *
     * Verifies that the application returns the required-fields message
     * and does not insert an employee into the database.
     */
    public function testMissingFirstName(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'last_name' => 'Doe',
                'email'     => 'john@test.com',
                'role_id'   => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'All fields are required.',
            $response['body']
        );

        $this->assertFalse(
            $this->getEmployeeByEmail('john@test.com')
        );
    }

    /**
     * Tests that an employee cannot be created without a last name.
     */
    public function testMissingLastName(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'email'      => 'john@test.com',
                'role_id'    => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'All fields are required.',
            $response['body']
        );

        $this->assertFalse(
            $this->getEmployeeByEmail('john@test.com')
        );
    }

    /**
     * Tests that an employee cannot be created without an email address.
     */
    public function testMissingEmail(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'role_id'    => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'All fields are required.',
            $response['body']
        );
    }

    /**
     * Tests that an employee cannot be created without a role.
     */
    public function testMissingRole(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john@test.com'
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'All fields are required.',
            $response['body']
        );

        $this->assertFalse(
            $this->getEmployeeByEmail('john@test.com')
        );
    }

    /**
     * Tests that leading and trailing whitespace is removed from input fields.
     *
     * The employee should be stored using the trimmed first name, last name,
     * and email address.
     */
    public function testWhitespaceIsTrimmed(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => '  John  ',
                'last_name'  => '  Doe  ',
                'email'      => '  john@test.com  ',
                'role_id'    => 1
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Employee Created Successfully',
            $response['body']
        );

        $user = $this->getEmployeeByEmail('john@test.com');

        $this->assertNotFalse($user);
        $this->assertEquals('John', $user['first_name']);
        $this->assertEquals('Doe', $user['last_name']);
        $this->assertEquals('john@test.com', $user['email']);
    }

    /**
     * Tests that an employee can be created with the Manager role.
     */
    public function testManagerRole(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'email'      => 'jane@test.com',
                'role_id'    => 2
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Employee Created Successfully',
            $response['body']
        );

        $user = $this->getEmployeeByEmail('jane@test.com');

        $this->assertNotFalse($user);
        $this->assertEquals(2, $user['role_id']);
    }

    /**
     * Tests that an employee can be created with the Admin role.
     */
    public function testAdminRole(): void
    {
        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'Admin',
                'last_name'  => 'User',
                'email'      => 'admin@test.com',
                'role_id'    => 3
            ]
        );

        $this->assertEquals(200, $response['status']);

        $this->assertStringContainsString(
            'Employee Created Successfully',
            $response['body']
        );

        $user = $this->getEmployeeByEmail('admin@test.com');

        $this->assertNotFalse($user);
        $this->assertEquals(3, $user['role_id']);
    }

    /**
     * Tests that multiple employees receive different five-digit PINs.
     *
     * This verifies that the PIN generation logic does not normally assign
     * the same PIN to two employees.
     */
    public function testMultipleUsersReceiveDifferentPins(): void
    {
        $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'john@test.com',
                'role_id'    => 1
            ]
        );

        $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'email'      => 'jane@test.com',
                'role_id'    => 1
            ]
        );

        $john = $this->getEmployeeByEmail('john@test.com');
        $jane = $this->getEmployeeByEmail('jane@test.com');

        $this->assertNotFalse($john);
        $this->assertNotFalse($jane);

        $this->assertNotSame(
            $john['pin'],
            $jane['pin']
        );
    }

    /**
     * Tests the database constraint that prevents duplicate email addresses.
     *
     * The current implementation does not explicitly handle the database
     * exception, so the second request is expected to return HTTP 500.
     *
     * This test confirms that the database still contains only one employee
     * with the duplicate email address.
     */
    public function testDuplicateEmailIsRejected(): void
    {
        $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'John',
                'last_name'  => 'Doe',
                'email'      => 'duplicate@test.com',
                'role_id'    => 1
            ]
        );

        $response = $this->post(
            'http://127.0.0.1:8000/create_user.php',
            [
                'first_name' => 'Jane',
                'last_name'  => 'Smith',
                'email'      => 'duplicate@test.com',
                'role_id'    => 1
            ]
        );

        // The application currently exposes the database exception as HTTP 500.
        $this->assertEquals(500, $response['status']);

        // Verify that the duplicate request did not create another employee.
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM EMPLOYEES WHERE email = ?"
        );

        $stmt->execute(['duplicate@test.com']);

        $count = $stmt->fetchColumn();

        $this->assertEquals(1, $count);
    }
}
