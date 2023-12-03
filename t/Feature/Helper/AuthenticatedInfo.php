<?php

namespace Tests\Feature\Helper;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Faker\Factory;

class AuthenticatedInfo
{
    private $request;
    private User $user;
    private string $password;

    public function __construct($request, User $user, string $password) {
        $this->request = $request;
        $this->user = $user;
        $this->password = $password;
    }

    public function getRequest() {
        return $this->request;
    }

    public function getUser(): User {
        return $this->user;
    }

    public function getPassword(): string {
        return $this->password;
    }
}
