<?php

namespace Tests\Feature\Helper;

use CodeIgniter\Shield\Test\AuthenticationTesting;

class AuthenticatedHTTPTestCase extends HTTPTestCase
{
    use AuthenticationTesting;

    protected function actAsAuthenticatedUser($session_details = null) {
        return $this->actingAs($this->user)->withSession($session_details);
    }
}
