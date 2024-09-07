<?php

namespace Tests\Feature\Helper;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Faker\Factory;

class AuthenticatedHTTPTestCase extends HTTPTestCase
{
    use AuthenticationTesting;

    protected function makeAuthenticatedInfo($session_details = null): AuthenticatedInfo
    {
        $user_info = $this->makeUserWithPlaintextPassword();
        $user_entity = $user_info["entity"];
        $password = $user_info["password"];

        return new AuthenticatedInfo(
            $this->actingAs($user_entity)->withSession($session_details),
            $user_entity,
            $password
        );
    }

    protected function makeUser(): User
    {
        return $this->makeUserWithPlaintextPassword()["entity"];
    }

    private function makeUserWithPlaintextPassword(): array
    {
        $faker = Factory::create();
        $password = $faker->password();
        $user_data = [
            "email" => $faker->email(),
            "username" => $faker->userName(),
            "password" => $password,
            "password_confirm" => $password
        ];
        $user_entity = new User();
        $user_entity->fill($user_data);

        $provider = model(setting("Auth.userProvider"));
        $provider->save($user_entity);
        $user_entity = $provider->findById($provider->getInsertID());

        return [
            "entity" => $user_entity,
            "password" => $password
        ];
    }
}
