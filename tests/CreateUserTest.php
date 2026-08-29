<?php

require_once __DIR__ . '/../test_helpers/BaseWebTest.php';

class CreateUserTest extends BaseWebTest
{
    public function testCreateUserSuccess(): void
    {
        $output = $this->post(__DIR__ . '/../create_user.php', [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john@test.com',
            'role_id'    => 1
        ]);

        $this->assertStringContainsString(
            'Employee Created Successfully',
            $output
        );

        $user = $this->getEmployeeByEmail('john@test.com');

        $this->assertNotFalse($user);
        $this->assertEquals('John', $user['first_name']);
        $this->assertEquals('Doe', $user['last_name']);
        $this->assertEquals(1, $user['role_id']);

        $this->assertNotEmpty($user['pin']);
        $this->assertEquals(5, strlen($user['pin']));
    }
}
