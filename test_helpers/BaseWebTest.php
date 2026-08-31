<?php

use PHPUnit\Framework\TestCase;

abstract class BaseWebTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') .
                ";port=3306;dbname=" . getenv('DB_NAME') .
                ";charset=utf8mb4",
            getenv('DB_USER'),
            getenv('DB_PASS')
        );

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Start each test with a clean database.
        $this->pdo->exec("DELETE FROM AUDIT_LOG");
        $this->pdo->exec("DELETE FROM WORK_TIMES");
        $this->pdo->exec("DELETE FROM EMPLOYEES");
    }

    protected function post(string $url, array $postData): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
        ]);

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

    protected function getEmployeeByEmail(string $email): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM EMPLOYEES WHERE email = ?
        ");

        $stmt->execute([$email]);

        return $stmt->fetch();
    }
}
