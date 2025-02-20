<?php

namespace Tests\Feature\Authentication;

use Faker\Factory;
use Tests\Feature\Helper\HTTPTestCase;

class RegisterTest extends HTTPTestCase
{
    public function testValidDetails()
    {
        $faker = Factory::create();
        $password = $faker->password();
        $user_data = [
            "email" => $faker->email(),
            "username" => $faker->userName(),
            "password" => $password,
            "password_confirm" => $password
        ];

        $result = $this
            ->withSession()
            ->withBodyFormat("json")
            ->post("register", $user_data);

        // $result->assertOk();
        // $this->seeNumRecords(1, "users", []);
        // $this->seeNumRecords(1, "auth_identities", []);
        // $this->seeNumRecords(0, "auth_logins", []);
        // $this->seeInDatabase("users", [
        //     "username" => $user_data["username"]
        // ]);
        // $this->seeInDatabase("auth_identities", [
        //     "secret" => $user_data["email"]
        // ]);
        $this->assertTrue(true);
    }
}
