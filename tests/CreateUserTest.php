<?php

require_once __DIR__ . '/../src/CreateUserDynamic.php';

use PHPUnit\Framework\TestCase;

class CreateUserTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE EMPLOYEES (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT,
                last_name TEXT,
                email TEXT UNIQUE,
                pin TEXT,
                role_id INTEGER
            )
        ");
    }

    public function testCreateUserSuccessfully()
    {
        $userId = createUser(
            $this->pdo,
            "John",
            "Doe",
            "john@example.com",
            "1234",
            1
        );

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);

        $stmt = $this->pdo->query("SELECT * FROM EMPLOYEES WHERE id = $userId");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($user);
        $this->assertEquals("John", $user['first_name']);
        $this->assertEquals("Doe", $user['last_name']);
        $this->assertEquals("john@example.com", $user['email']);
        $this->assertEquals(1, $user['role_id']);
        $this->assertTrue(password_verify("1234", $user['pin']));
    }

    public function testThrowsExceptionForEmptyFields()
    {
        $this->expectException(InvalidArgumentException::class);

        createUser(
            $this->pdo,
            "",
            "Doe",
            "john@example.com",
            "1234",
            1
        );
    }

    public function testThrowsExceptionForInvalidEmail()
    {
        $this->expectException(InvalidArgumentException::class);

        createUser(
            $this->pdo,
            "John",
            "Doe",
            "not-an-email",
            "1234",
            1
        );
    }

    public function testThrowsExceptionForShortPin()
    {
        $this->expectException(InvalidArgumentException::class);

        createUser(
            $this->pdo,
            "John",
            "Doe",
            "john@example.com",
            "12",
            1
        );
    }

    public function testDuplicateEmailThrowsException()
    {
        createUser(
            $this->pdo,
            "John",
            "Doe",
            "john@example.com",
            "1234",
            1
        );

        $this->expectException(RuntimeException::class);

        createUser(
            $this->pdo,
            "Jane",
            "Smith",
            "john@example.com",
            "5678",
            2
        );
    }

    public function testEmailIsNormalized()
    {
        $userId = createUser(
            $this->pdo,
            "John",
            "Doe",
            "  JOHN@EXAMPLE.COM  ",
            "1234",
            1
        );

        $stmt = $this->pdo->query("SELECT * FROM EMPLOYEES WHERE id = $userId");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals("john@example.com", $user['email']);
    }

    public function testPinExactlyFourCharacters()
    {
        $userId = createUser(
            $this->pdo,
            "Jane",
            "Doe",
            "jane@example.com",
            "1234",
            1
        );

        $this->assertIsInt($userId);
    }

    public function testWhitespaceOnlyFails()
    {
        $this->expectException(InvalidArgumentException::class);

        createUser(
            $this->pdo,
            "   ",
            "Doe",
            "john@example.com",
            "1234",
            1
        );
    }

    public function testMultipleUsersCanBeCreated()
    {
        $id1 = createUser($this->pdo, "A", "B", "a@test.com", "1234", 1);
        $id2 = createUser($this->pdo, "C", "D", "c@test.com", "5678", 2);

        $this->assertNotEquals($id1, $id2);
    }
}
