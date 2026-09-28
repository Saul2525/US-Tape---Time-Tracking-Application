<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class ManagerDashboardTest extends BaseWebTest
{
    // --------------------------------------------------
    // Access Control
    // --------------------------------------------------

    public function testManagerCanAccessDashboard(): void
    {
        // Role 2 is the Manager role from db.sql.
        $sessionId = $this->createSession(2);

        // Access the manager dashboard as a manager.
        $response = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $sessionId
        );

        // Managers should be allowed to access the dashboard.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Manager Dashboard',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    public function testEmployeeCannotAccessDashboard(): void
    {
        // Role 1 is the Employee role.
        $sessionId = $this->createSession(1);

        // Attempt to access the manager dashboard.
        $response = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $sessionId
        );

        // Employees do not have can_edit_others permission,
        // so the dashboard should reject the request.
        $this->assertStringContainsString(
            'Access denied.',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    public function testAdminCanAccessDashboard(): void
    {
        // Role 3 is the Admin role.
        $sessionId = $this->createSession(3);

        // Access the dashboard as an administrator.
        $response = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $sessionId
        );

        // Admins inherit the permission to edit other employees,
        // so they should also be able to use the dashboard.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'Manager Dashboard',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Employee Display
    // --------------------------------------------------

    public function testOnlyActiveEmployeesAreDisplayed(): void
    {
        // Create one active and one inactive employee.
        $activeId = $this->createEmployee(
            'Alice',
            'Active',
            'alice@example.com',
            1
        );

        $inactiveId = $this->createEmployee(
            'Bob',
            'Inactive',
            'bob@example.com',
            1
        );

        $this->pdo->prepare(
            'UPDATE EMPLOYEES SET is_active = 0 WHERE employee_id = ?'
        )->execute([$inactiveId]);

        $sessionId = $this->createSession(2);

        // Load the dashboard.
        $response = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $sessionId
        );

        // Active employees should be displayed.
        $this->assertStringContainsString(
            'Alice Active',
            $response['body']
        );
        $this->assertStringContainsString(
            'alice@example.com',
            $response['body']
        );

        // Inactive employees should not appear in the dashboard.
        $this->assertStringNotContainsString(
            'Bob Inactive',
            $response['body']
        );
        $this->assertStringNotContainsString(
            'bob@example.com',
            $response['body']
        );

        // The IDs themselves are not displayed as employee text,
        // but this assertion confirms our test data was distinct.
        $this->assertNotSame($activeId, $inactiveId);

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Search Filtering
    // --------------------------------------------------

    public function testSearchFiltersEmployeesByName(): void
    {
        // Create multiple active employees with different names.
        $this->createEmployee(
            'Alice',
            'Smith',
            'alice@example.com',
            1
        );

        $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        $sessionId = $this->createSession(2);

        // Search specifically for Alice.
        $response = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [
                'search' => 'Alice',
            ],
            $sessionId
        );

        // Alice should appear in the filtered results.
        $this->assertStringContainsString(
            'Alice Smith',
            $response['body']
        );
        $this->assertStringContainsString(
            'alice@example.com',
            $response['body']
        );

        // John should not appear because the search is filtered
        // to employees whose full name contains "Alice".
        $this->assertStringNotContainsString(
            'John Doe',
            $response['body']
        );
        $this->assertStringNotContainsString(
            'john@example.com',
            $response['body']
        );

        $this->destroySession($sessionId);
    }

    // --------------------------------------------------
    // Role-Specific Dashboard Controls
    // --------------------------------------------------

    public function testOnlyAdminsSeeEmployeeManagementLink(): void
    {
        // Create separate sessions for a manager and administrator.
        $managerSession = $this->createSession(2);

        // Managers can edit other employees but cannot manage
        // employee accounts.
        $managerResponse = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $managerSession
        );

        // The employee-management link should not be shown to managers.
        $this->assertStringNotContainsString(
            'href="admin.php"',
            $managerResponse['body']
        );

        $this->destroySession($managerSession);

        // Create an administrator session.
        $adminSession = $this->createSession(3);

        // Admins have can_manage_users permission.
        $adminResponse = $this->get(
            $this->baseUrl . '/manager_dashboard.php',
            [],
            $adminSession
        );

        // The employee-management link should be available to admins.
        $this->assertStringContainsString(
            'href="admin.php"',
            $adminResponse['body']
        );

        $this->destroySession($adminSession);
    }
}
