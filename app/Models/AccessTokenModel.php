<?php

namespace App\Models;

use App\Contracts\OwnedResource;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\TokenLoginModel;
use Faker\Generator;

class AccessTokenModel extends TokenLoginModel implements OwnedResource
{
    protected $allowedFields = [
        "deleted_at"
    ];

    protected $sortable_fields = [
        "created_at",
        "updated_at",
        "deleted_at"
    ];


    public function isOwnedBy(User $user, string $search_mode, int $resource_id): bool
    {
        $match = $this
            ->limitSearchToUser($this->getSearchQuery($search_mode), $user)
            ->find($resource_id);
        return !is_null($match);
    }

    public function limitSearchToUser(OwnedResource $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }

    private function getSearchQuery(string $search_mode)
    {
        if ($search_mode === SEARCH_WITH_DELETED) {
            return $this->withDeleted();
        } elseif ($search_mode === SEARCH_ONLY_DELETED) {
            return $this->onlyDeleted();
        }

        return $this;
    }
}
