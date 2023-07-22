<?php

namespace App\Contracts;

use CodeIgniter\Shield\Models\UserModel;

interface OwnedResource
{
    public function isOwnedBy(UserModel $user): bool;
}
