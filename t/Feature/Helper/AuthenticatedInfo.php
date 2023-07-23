<?php

namespace Tests\Feature\Helper;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Faker\Factory;

class AuthenticatedInfo
{
    private $request;
    private User $user;

    public function __construct($request, User $user) {
        $this->request = $request;
        $this->user = $user;
    }

    public function getRequest() {
        return $this->request;
    }

    public function getUser() {
        return $this->user;
    }
}
