<?php

function createUser(
    PDO $pdo,
    string $firstName,
    string $lastName,
    string $email,
    string $pin,
    int $roleId
): int {
    $firstName = trim($firstName);
    $lastName  = trim($lastName);
    $email     = trim(strtolower($email));
    $pin       = trim($pin);

    if ($firstName === '' || $lastName === '' || $email === '' || $pin === '') {
        throw new InvalidArgumentException("All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException("Invalid email address.");
    }

    if (strlen($pin) < 4) {
        throw new InvalidArgumentException("PIN must be at least 4 characters.");
    }

    $hashedPin = password_hash($pin, PASSWORD_DEFAULT);
    if ($hashedPin === false) {
        throw new RuntimeException("Failed to hash PIN.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO EMPLOYEES (first_name, last_name, email, pin, role_id)
        VALUES (:first_name, :last_name, :email, :pin, :role_id)
    ");

    try {
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name'  => $lastName,
            ':email'      => $email,
            ':pin'        => $hashedPin,
            ':role_id'    => $roleId
        ]);

        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new RuntimeException("Email already exists.");
        }
        throw $e;
    }
}
