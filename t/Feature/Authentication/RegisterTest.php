<?php

namespace Tests\Feature\Authentication;

use Tests\Feature\Helper\HTTPTestCase;

use Faker\Factory;

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

        $result = $this->withBodyFormat("json")->withHeaders([
            "Accept" => "application/json"
        ])->post("register", $user_data);

        $result->assertOk();
        $this->seeInDatabase("users", [
            "username" => $user_data["username"]
        ]);
        $this->seeInDatabase("auth_identities", [
            "secret" => $user_data["email"]
        ]);
    }
}
