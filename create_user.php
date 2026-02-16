<?php
require 'config.php';

$hashedPin = password_hash("1234", PASSWORD_DEFAULT);

$pdo->prepare("
    INSERT INTO EMPLOYEES 
    (first_name, last_name, email, pin, role_id)
    VALUES (?, ?, ?, ?, ?)
")->execute([
    'John',
    'Doe',
    'john@company.com',
    $hashedPin,
    1
]);

echo "User created.";
?>
