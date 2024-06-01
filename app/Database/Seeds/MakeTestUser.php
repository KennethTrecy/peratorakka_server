<?php

namespace App\Database\Seeds;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

class MakeTestUser extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        $user = new User([
            'username' => 'Test Account',
            'email'    => 'test@example.com',
            'password' => '12345678',
        ]);
        $users->save($user);

        $user_id = $users->getInsertID();



    }
}
