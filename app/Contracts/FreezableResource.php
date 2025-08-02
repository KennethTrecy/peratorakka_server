<?php

namespace App\Contracts;

use CodeIgniter\Entity\Entity;

interface FreezableResource
{
    public function findFrozen(int $resource_id): ?Entity;
}
