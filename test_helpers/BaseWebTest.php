<?php

use PHPUnit\Framework\TestCase;

abstract class BaseWebTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=timeclock;charset=utf8mb4',
            'root',
            'root'
        );

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    protected function post(string $filePath, array $postData): string
    {
        $_POST = $postData;

        ob_start();

        include $filePath;

        return ob_get_clean();
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
