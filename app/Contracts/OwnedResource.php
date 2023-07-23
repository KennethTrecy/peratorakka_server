<?php

namespace App\Contracts;

use CodeIgniter\Shield\Entities\User;

interface OwnedResource
{
    public function isOwnedBy(User $user, int $resource_id): bool;
}
