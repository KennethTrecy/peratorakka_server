<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Interfaces\FabricatorModel;
use Faker\Generator;

use App\Contracts\OwnedResource;

abstract class BaseResourceModel extends Model implements FabricatorModel, OwnedResource
{
    protected $primaryKey = "id";
    protected $useAutoIncrement = true;
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = "datetime";
    protected $createdField = "created_at";
    protected $updatedField = "updated_at";
    protected $deletedField = "deleted_at";

    // Validation
    protected $validationRules = [];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    abstract public function limitSearchToUser(BaseResourceModel $query_builder, User $user);

    public function isOwnedBy(User $user, string $search_mode, int $resource_id): bool {
        $match = $this
            ->limitSearchToUser($this->getSearchQuery($search_mode), $user)
            ->find($resource_id);
        return !is_null($match);
    }

    private function getSearchQuery(string $search_mode) {
        if ($search_mode === SEARCH_WITH_DELETED) return $this->withDeleted();
        else if ($search_mode === SEARCH_ONLY_DELETED) return $this->onlyDeleted();

        return $this;
    }
}
