<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;
use Faker\Factory;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CurrencyModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

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
        $tableNames = config("Auth")->tables;
        $this->seeInDatabase($tableNames["users"], [
            "id" => $authenticated_info->getUser()->id,
            "username" => $user_data["username"]
        ]);
        $this->seeInDatabase($tableNames["identities"], [
            "user_id" => $authenticated_info->getUser()->id,
            "secret" => $user_data["email"]
        ]);
    }
}
