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

        $result = $this->withSession()->withHeaders([
            "Accept" => "application/json"
        ])->post("register", $user_data);

        $result->assertRedirect();
        sleep(2);
        $this->seeInDatabase("users", [
            "username" => $user_data["username"]
        ]);
        $this->seeInDatabase("auth_identities", [
            "secret" => $user_data["email"]
        ]);
        $this->seeNumRecords(1, "users", []);
        $this->seeNumRecords(1, "auth_identities", []);
        $this->seeNumRecords(0, "auth_logins", []);
    }
}
