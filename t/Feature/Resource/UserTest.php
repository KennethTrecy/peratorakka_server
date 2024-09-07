<?php

namespace Tests\Feature\Resource;

use CodeIgniter\Test\Fabricator;
use Faker\Factory;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class UserTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $faker = Factory::create();
        $password = $faker->password();
        $user_data = [
            "email" => $faker->email(),
            "username" => $faker->userName()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->patch("/api/v1/user", [
                "user" => $user_data
            ]);

        $result->assertStatus(204);
        $table_names = config("Auth")->tables;
        $this->seeInDatabase($table_names["users"], [
            "id" => $authenticated_info->getUser()->id,
            "username" => $user_data["username"]
        ]);
        $this->seeInDatabase($table_names["identities"], [
            "user_id" => $authenticated_info->getUser()->id,
            "secret" => $user_data["email"]
        ]);
    }

    public function testPasswordUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $faker = Factory::create();
        $new_password = "qa09uj65";
        $password_data = [
            "old_password" => $authenticated_info->getPassword(),
            "new_password" => $new_password,
            "confirm_new_password" => $new_password
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->patch("/api/v1/user/password", [
                "user" => $password_data
            ]);

        $result->assertStatus(204);
        $users = model(setting("Auth.userProvider"));
        $found_user = $users->findByCredentials([
            "email" => $authenticated_info->getUser()->email
        ]);
        $this->assertNotNull($found_user);
        $this->assertSame($found_user->id, $authenticated_info->getUser()->id);
        $password_service = service("passwords");
        $this->assertTrue($password_service->verify($new_password, $found_user->password_hash));
    }
}
