<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class AdminTest extends BaseWebTest
{
    // --------------------------------------------------
    // Access Control
    // --------------------------------------------------

    public function testNonAdminCannotAccessAdminPage(): void
    {
        // Create a manager session.
        // Managers can manage shifts, but they do not have
        // permission to manage employee accounts.
        $sessionId = $this->createSession(2);

        // Try to access the admin page as a manager.
        $response = $this->get(
            $this->baseUrl . '/admin.php',
            [],
            $sessionId
        );

        // The page should reject the request instead of
        // displaying employee-management functionality.
        $this->assertStringContainsString(
            'Access denied.',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    public function testAdminCanAccessAdminPage(): void
    {
        // Role 3 is the Admin role from db.sql.
        $sessionId = $this->createSession(3);

        // Access the employee-management page.
        $response = $this->get(
            $this->baseUrl . '/admin.php',
            [],
            $sessionId
        );

        // An administrator should be allowed to see the page.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Admin Employee Management',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Creation
    // --------------------------------------------------

    public function testAdminCanCreateEmployeeWithManualPin(): void
    {
        // Create an admin session so the request passes
        // the employee-management permission check.
        $sessionId = $this->createSession(3);

        // Submit a new employee using a manually selected
        // five-digit PIN.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'create_employee',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'role_id' => 1,
                'pin' => '54321',
            ],
            $sessionId
        );

        // The employee should be created successfully.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Employee created successfully.',
            $response['body']
        );

        // Verify that the employee was actually stored
        // in the database with the requested PIN.
        $employee = $this->getEmployeeByEmail('john@example.com');

        $this->assertNotFalse($employee);
        $this->assertSame('John', $employee['first_name']);
        $this->assertSame('Doe', $employee['last_name']);
        $this->assertSame('54321', $employee['pin']);
        $this->assertSame('1', (string) $employee['role_id']);
        $this->assertSame('1', (string) $employee['is_active']);

        $this->destroySession($sessionId);
    }

    public function testAdminCanCreateEmployeeWithAutomaticallyGeneratedPin(): void
    {
        // Leave the PIN blank so admin.php generates one automatically.
        $sessionId = $this->createSession(3);

        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'create_employee',
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@example.com',
                'role_id' => 1,
                'pin' => '',
            ],
            $sessionId
        );

        // The employee should still be created successfully.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Employee created successfully.',
            $response['body']
        );

        // Verify the generated PIN was saved and is exactly
        // five digits, as required by the application.
        $employee = $this->getEmployeeByEmail('alice@example.com');

        $this->assertNotFalse($employee);
        $this->assertMatchesRegularExpression(
            '/^\d{5}$/',
            $employee['pin']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Creation Validation
    // --------------------------------------------------

    public function testDuplicateEmailIsRejected(): void
    {
        // Create an employee that already uses the email address.
        $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $sessionId = $this->createSession(3);

        // Attempt to create another employee with the same email.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'create_employee',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'role_id' => 1,
                'pin' => '54321',
            ],
            $sessionId
        );

        // The application should reject the duplicate instead of
        // creating a second employee with the same email.
        $this->assertStringContainsString(
            'An employee with this email already exists.',
            $response['body']
        );

        // There should still be only one employee with that email.
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM EMPLOYEES WHERE email = ?'
        );
        $stmt->execute(['john@example.com']);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $this->destroySession($sessionId);
    }

    public function testInvalidPinIsRejected(): void
    {
        $sessionId = $this->createSession(3);

        // The application requires a manual PIN to contain exactly
        // five digits.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'create_employee',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'role_id' => 1,
                'pin' => '123',
            ],
            $sessionId
        );

        // The invalid PIN should prevent employee creation.
        $this->assertStringContainsString(
            'PIN must be exactly 5 digits.',
            $response['body']
        );

        $this->assertFalse(
            $this->getEmployeeByEmail('john@example.com')
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Activation / Deactivation
    // --------------------------------------------------

    public function testAdminCanDeactivateEmployee(): void
    {
        // Create an active employee first.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $sessionId = $this->createSession(3);

        // Deactivate the employee through the admin page.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'deactivate_employee',
                'employee_id' => $employeeId,
            ],
            $sessionId
        );

        // The operation should report success.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Employee deactivated successfully.',
            $response['body']
        );

        // Verify the database state changed to inactive.
        $stmt = $this->pdo->prepare(
            'SELECT is_active FROM EMPLOYEES WHERE employee_id = ?'
        );
        $stmt->execute([$employeeId]);

        $this->assertSame(0, (int) $stmt->fetchColumn());

        $this->destroySession($sessionId);
    }

    public function testAdminCanActivateEmployee(): void
    {
        // Start with an employee that is already inactive.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $this->pdo->prepare(
            'UPDATE EMPLOYEES SET is_active = 0 WHERE employee_id = ?'
        )->execute([$employeeId]);

        $sessionId = $this->createSession(3);

        // Reactivate the employee through the admin page.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'activate_employee',
                'employee_id' => $employeeId,
            ],
            $sessionId
        );

        // The operation should report success.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Employee activated successfully.',
            $response['body']
        );

        // Verify that the employee is active again.
        $stmt = $this->pdo->prepare(
            'SELECT is_active FROM EMPLOYEES WHERE employee_id = ?'
        );
        $stmt->execute([$employeeId]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $this->destroySession($sessionId);
    }

    public function testAdminCannotDeactivateOwnAccount(): void
    {
        // Create the admin employee that will be logged in.
        $adminId = $this->createEmployee(
            'Admin',
            'User',
            'admin@example.com',
            3
        );

        // createUserSession() gives the request both the admin's
        // user_id and their administrator role.
        $sessionId = $this->createUserSession($adminId, 3);

        // Try to deactivate the account currently being used.
        $response = $this->post(
            $this->baseUrl . '/admin.php',
            [
                'action' => 'deactivate_employee',
                'employee_id' => $adminId,
            ],
            $sessionId
        );

        // The application should block the operation to prevent
        // the administrator from locking themselves out.
        $this->assertStringContainsString(
            'You cannot deactivate your own account.',
            $response['body']
        );

        // The administrator should remain active.
        $stmt = $this->pdo->prepare(
            'SELECT is_active FROM EMPLOYEES WHERE employee_id = ?'
        );
        $stmt->execute([$adminId]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $this->destroySession($sessionId);
    }
}
