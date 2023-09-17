<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Interfaces\FabricatorModel;
use Faker\Generator;

use App\Contracts\OwnedResource;
use App\Exceptions\UnprocessableRequest;

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

    // Custom attributes
    protected $available_search_modes = [
        SEARCH_NORMALLY,
        SEARCH_ONLY_DELETED
    ];
    protected $sortable_fields = [];
    protected $sortable_factors = [];

    abstract public function limitSearchToUser(BaseResourceModel $query_builder, User $user);

    public function filterList(BaseResourceModel $query_builder, array $options) {
        $filter_search_mode = $options["search_mode"] ?? SEARCH_NORMALLY;

        if (in_array($filter_search_mode, $this->available_search_modes, true)) {
            return $query_builder->getSearchQuery($filter_search_mode);
        } else {
            throw new UnprocessableRequest(
                "The search mode \"$filter_search_mode\" is unavailable."
            );
        }
    }

    public function sortList(BaseResourceModel $query_builder, array $options)  {
        $order_translation = [
            "ascending" => "ASC",
            "descending" => "DESC"
        ];

        foreach ($options as $option) {
            [ $criteria, $order ] = $option;
            $order = in_array($order, array_keys($order_translation))
                ? $order_translation[$order]
                : "ASC";

            if (in_array($criteria, $this->sortable_fields)) {
                $query_builder = $query_builder->orderBy($criteria, $order);
            } else if (in_array($criteria, $this->sortable_factors)) {
                $query_builder = $this->sortListByFactor($query_builder, $criteria, $order);
            } else {
                throw new UnprocessableRequest(
                    "The criteria \"$criteria\" is unavailable for sorting."
                );
            }
        }

        return $query_builder;
    }

    protected function sortListByFactor(
        BaseResourceModel $query_builder,
        string $factor_name,
        string $order
    ) {
        return $query_builder;
    }

    public function paginateList(BaseResourceModel $query_builder, array $options) {
        return $query_builder;
    }

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
