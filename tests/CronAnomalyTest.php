<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class CronAnomalyTest extends BaseWebTest
{
    // --------------------------------------------------
    // Anomaly Detection
    // --------------------------------------------------

    public function testOpenShiftOlderThanTenHoursIsFlagged(): void
    {
        // Create an employee whose shift will be tested.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an open shift from more than 10 hours ago.
        $this->createWorkTime(
            $employeeId,
            date('Y-m-d H:i:s', strtotime('-11 hours')),
            null
        );

        // Run the anomaly detection script.
        $response = $this->get(
            $this->baseUrl . '/cron_anomaly.php'
        );

        // The old open shift should be reported as an anomaly.
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            "ALERT: Employee {$employeeId} open shift since",
            $response['body']
        );
    }

    public function testOpenShiftExactlyTenHoursOldIsNotFlagged(): void
    {
        // Create an employee for the boundary-condition test.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // Create an open shift exactly 10 hours ago.
        // The application only flags shifts where the difference
        // is greater than 10 hours.
        $this->createWorkTime(
            $employeeId,
            date('Y-m-d H:i:s', strtotime('-10 hours')),
            null
        );

        // Run the anomaly detection script.
        $response = $this->get(
            $this->baseUrl . '/cron_anomaly.php'
        );

        // The employee should not appear in the alert output.
        $this->assertStringNotContainsString(
            "ALERT: Employee {$employeeId}",
            $response['body']
        );
    }

    public function testClosedShiftIsNotFlagged(): void
    {
        // Create an employee with an old but completed shift.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // The shift started 12 hours ago but ended 4 hours later.
        // Because clock_out_time is set, it should not be considered
        // an open-shift anomaly.
        $this->createWorkTime(
            $employeeId,
            date('Y-m-d H:i:s', strtotime('-12 hours')),
            date('Y-m-d H:i:s', strtotime('-8 hours'))
        );

        // Run the anomaly detection script.
        $response = $this->get(
            $this->baseUrl . '/cron_anomaly.php'
        );

        // Completed shifts should never be included.
        $this->assertStringNotContainsString(
            "ALERT: Employee {$employeeId}",
            $response['body']
        );
    }

    public function testRecentOpenShiftIsNotFlagged(): void
    {
        // Create an employee with a recent open shift.
        $employeeId = $this->createEmployee(
            'John',
            'Doe',
            'john@example.com',
            1
        );

        // This shift has only been open for 5 hours, so it should
        // remain below the anomaly threshold.
        $this->createWorkTime(
            $employeeId,
            date('Y-m-d H:i:s', strtotime('-5 hours')),
            null
        );

        // Run the anomaly detection script.
        $response = $this->get(
            $this->baseUrl . '/cron_anomaly.php'
        );

        // Recent open shifts should not produce an alert.
        $this->assertStringNotContainsString(
            "ALERT: Employee {$employeeId}",
            $response['body']
        );
    }
}
