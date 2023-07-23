<?php

namespace Tests\Feature\Helper;

use CodeIgniter\Shield\Test\AuthenticationTesting;

class AuthenticatedHTTPTestCase extends HTTPTestCase
{
    use AuthenticationTesting;

    protected function actAsAuthenticatedUser() {
        return $this->actingAs($this->user);
    }
}
