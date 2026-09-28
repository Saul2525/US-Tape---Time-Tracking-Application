<?php

use PHPUnit\Framework\TestCase;

abstract class BaseWebTest extends TestCase
{
    protected PDO $pdo;
    protected string $baseUrl = 'http://127.0.0.1:8001';

    // --------------------------------------------------
    // Database Setup
    // --------------------------------------------------

    protected function setUp(): void
    {
        $dbName = getenv('DB_NAME');

        // Safety check: never allow tests to modify the real database.
        if ($dbName !== 'timeclock_test') {
            $this->fail(
                "Tests must use the timeclock_test database. " .
                    "Current database: " . ($dbName ?: 'not set') . "."
            );
        }

        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') .
                ";port=3306;dbname=" . $dbName .
                ";charset=utf8mb4",
            getenv('DB_USER'),
            getenv('DB_PASS')
        );

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Start each test with a clean TEST database.
        $this->pdo->exec("DELETE FROM AUDIT_LOG");
        $this->pdo->exec("DELETE FROM WORK_TIMES");
        $this->pdo->exec("DELETE FROM EMPLOYEES");
    }

    // --------------------------------------------------
    // Test Data Helpers
    // --------------------------------------------------

    protected function createEmployee(
        string $firstName,
        string $lastName,
        string $email,
        int $roleId
    ): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO EMPLOYEES
                (first_name, last_name, email, pin, role_id)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            '12345',
            $roleId
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function createWorkTime(
        int $employeeId,
        string $clockIn,
        ?string $clockOut,
        int $approved = 0,
        int $edited = 0
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO WORK_TIMES
                (employee_id, clock_in_time, clock_out_time, approved, is_edited)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $employeeId,
            $clockIn,
            $clockOut,
            $approved,
            $edited
        ]);
    }

    // --------------------------------------------------
    // Session Helpers
    // --------------------------------------------------

    protected function createSession(int $roleId): string
    {
        $sessionId = bin2hex(random_bytes(16));

        session_id($sessionId);
        session_start();

        $_SESSION['role_id'] = $roleId;

        session_write_close();

        return $sessionId;
    }

    protected function createUserSession(
        int $userId,
        int $roleId
    ): string {
        $sessionId = bin2hex(random_bytes(16));

        session_id($sessionId);
        session_start();

        // Store both the logged-in user's ID and their role.
        // update_shift.php uses user_id when creating the audit log.
        $_SESSION['user_id'] = $userId;
        $_SESSION['role_id'] = $roleId;

        session_write_close();

        return $sessionId;
    }

    protected function destroySession(string $sessionId): void
    {
        session_id($sessionId);
        session_start();

        $_SESSION = [];

        session_destroy();
    }

    // --------------------------------------------------
    // HTTP Helpers
    // --------------------------------------------------

    protected function get(
        string $url,
        array $query = [],
        ?string $sessionId = null
    ): array {
        $queryString = http_build_query($query);

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
        ];

        if ($sessionId !== null) {
            $options[CURLOPT_COOKIE] =
                session_name() . '=' . $sessionId;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);

            $this->fail("HTTP request failed: " . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        return [
            'status' => $status,
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    protected function post(
        string $url,
        array $postData,
        ?string $sessionId = null
    ): array {
        $ch = curl_init($url);

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
        ];

        if ($sessionId !== null) {
            $options[CURLOPT_COOKIE] =
                session_name() . '=' . $sessionId;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);

            $this->fail("HTTP request failed: " . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status' => $statusCode,
            'body' => $response
        ];
    }

    // --------------------------------------------------
    // Database Lookup Helpers
    // --------------------------------------------------

    protected function getEmployeeByEmail(string $email): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM EMPLOYEES WHERE email = ?
        ");

        $stmt->execute([$email]);

        return $stmt->fetch();
    }
}
