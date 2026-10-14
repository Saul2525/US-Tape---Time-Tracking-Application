<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class ManagerLoginTest extends BaseWebTest
{
    // --------------------------------------------------
    // Test Helper
    // --------------------------------------------------

    private function createLoginEmployee(
        string $email,
        string $password,
        int $roleId,
        int $active = 1
    ): int {
        // Create an employee with the requested role.
        $employeeId = $this->createEmployee(
            'Test',
            'User',
            $email,
            $roleId
        );

        // Set the employee's password and account status
        // so the login tests can use the desired credentials.
        $stmt = $this->pdo->prepare("
            UPDATE EMPLOYEES
            SET password = ?, is_active = ?
            WHERE employee_id = ?
        ");

        $stmt->execute([$password, $active, $employeeId]);

        return $employeeId;
    }

    // --------------------------------------------------
    // Successful Login
    // --------------------------------------------------

    public function testAdminCanLogIn(): void
    {
        // Create an administrator account with a known password.
        // Role 3 represents the Admin role.
        $this->createLoginEmployee(
            'admin@example.com',
            'testpass',
            3
        );

        // Submit the administrator's login credentials.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'admin@example.com',
                'password' => 'testpass'
            ]
        );

        // A successful login should redirect to the manager dashboard.
        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString(
            'Location: manager_dashboard.php',
            $response['headers']
        );
    }

    public function testManagerCanLogIn(): void
    {
        // Create a manager account with a known password.
        // Role 2 represents the Manager role.
        $this->createLoginEmployee(
            'manager@example.com',
            'testpass',
            2
        );

        // Submit the manager's login credentials.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'manager@example.com',
                'password' => 'testpass'
            ]
        );

        // A successful login should redirect to the manager dashboard.
        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString(
            'Location: manager_dashboard.php',
            $response['headers']
        );
    }

    // --------------------------------------------------
    // Invalid Credentials
    // --------------------------------------------------

    public function testIncorrectPasswordIsRejected(): void
    {
        // Create a manager account with a known password.
        $this->createLoginEmployee(
            'manager@example.com',
            'testpass',
            2
        );

        // Attempt to log in with an incorrect password.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'manager@example.com',
                'password' => 'wrongpass'
            ]
        );

        // The application should reject the credentials
        // instead of redirecting to the manager dashboard.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid credentials.',
            $response['body']
        );
    }

    public function testUnknownEmailIsRejected(): void
    {
        // Attempt to log in with an email that does not
        // belong to an employee in the test database.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'unknown@example.com',
                'password' => 'testpass'
            ]
        );

        // The application should reject the unknown account.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid credentials.',
            $response['body']
        );
    }

    // --------------------------------------------------
    // Account Status and Role Restrictions
    // --------------------------------------------------

    public function testInactiveManagerCannotLogIn(): void
    {
        // Create a manager account that is inactive.
        // Role 2 represents the Manager role, and 0 means inactive.
        $this->createLoginEmployee(
            'inactive@example.com',
            'testpass',
            2,
            0
        );

        // Attempt to log in with the inactive manager's credentials.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'inactive@example.com',
                'password' => 'testpass'
            ]
        );

        // Inactive accounts should not be allowed to log in.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Invalid credentials.',
            $response['body']
        );
    }

    public function testEmployeeRoleCannotLogIn(): void
    {
        // Create an active employee account.
        // Role 1 represents a regular employee, not a manager or admin.
        $this->createLoginEmployee(
            'employee@example.com',
            'testpass',
            1
        );

        // Attempt to access the manager login using employee credentials.
        $response = $this->post(
            $this->baseUrl . '/manager_login.php',
            [
                'email' => 'employee@example.com',
                'password' => 'testpass'
            ]
        );

        // Regular employees should be denied access to the manager area.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Access denied.',
            $response['body']
        );
    }
}
