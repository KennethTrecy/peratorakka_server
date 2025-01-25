<?php

namespace App\Models;

use App\Contracts\OwnedResource;
use App\Exceptions\UnprocessableRequest;
use App\Libraries\Resource;
use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Interfaces\FabricatorModel;
use Faker\Generator;

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
        SEARCH_ONLY_DELETED,
        SEARCH_WITH_DELETED
    ];
    protected $sortable_fields = [];
    protected $sortable_factors = [];

    abstract public function limitSearchToUser(BaseResourceModel $query_builder, User $user);

    public function filterList(BaseResourceModel $query_builder, array $options)
    {
        $filter_search_mode = strtoupper($options["search_mode"] ?? SEARCH_NORMALLY);

        if (in_array($filter_search_mode, $this->available_search_modes, true)) {
            return $query_builder->getSearchQuery($filter_search_mode);
        } else {
            throw new UnprocessableRequest(
                "The search mode \"$filter_search_mode\" is unavailable."
            );
        }
    }

    public function sortList(BaseResourceModel $query_builder, array $options)
    {
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
            } elseif (in_array($criteria, $this->sortable_factors)) {
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

    public function paginateList(BaseResourceModel $query_builder, array $options)
    {
        return $query_builder;
    }

    public function isOwnedBy(User $user, string $search_mode, int $resource_id): bool
    {
        $match = $this
            ->limitSearchToUser($this->getSearchQuery($search_mode), $user)
            ->find($resource_id);
        return !is_null($match);
    }

    public function selectUsingMultipleIDs($IDs): array
    {
        $resources = [];
        if (count($IDs) > 0) {
            $resources = $this
                ->whereIn("id", array_unique($IDs))
                ->withDeleted()
                ->orderBy("created_at", "ASC")
                ->findAll();
        }

        return $resources;
    }

    public function selectWithAncestorsUsingMultipleIDs(array $IDs): array
    {
        $resources = $this->selectUsingMultipleIDs($IDs);
        $resolved_ancestors = static::selectAncestorsWithResolvedResources($resources);
        return array_merge([ $resources ], $resolved_ancestors);
    }

    public static function selectAncestorsWithResolvedResources(array $resources): array
    {
        $direct_ancestor_information = static::identifyAncestors();
        $newly_discovered_ancestors = array_keys($direct_ancestor_information);
        $inverse_hierarchy = array_map(
            function () { return []; },
            array_flip($newly_discovered_ancestors)
        );
        while (count($newly_discovered_ancestors) > 0) {
            $newly_discovered_ancestor = array_shift($newly_discovered_ancestors);

            $ancestor_model = model($newly_discovered_ancestor);
            $indirect_ancestor_information = $ancestor_model::identifyAncestors();
            $parent_ancestors = array_keys($indirect_ancestor_information);
            $newly_discovered_ancestors = array_merge(
                $newly_discovered_ancestors,
                array_diff($parent_ancestors, array_keys($inverse_hierarchy))
            );
            $inverse_hierarchy[$newly_discovered_ancestor] = $parent_ancestors;
        }

        $normal_hierarchy = array_map(
            function () { return []; },
            array_flip(array_keys($inverse_hierarchy))
        );
        foreach ($inverse_hierarchy as $child_ancestor => $parent_ancestors) {
            foreach ($parent_ancestors as $parent_ancestor) {
                $normal_hierarchy[$parent_ancestor][] = $child_ancestor;
            }
        }

        $resolved_ancestors = array_map(function () { return []; }, $inverse_hierarchy);
        $incomplete_ancestor_IDs = array_map(function () { return []; }, $normal_hierarchy);

        foreach ($direct_ancestor_information as $ancestor_class => $column_IDs) {
            foreach ($column_IDs as $column_id) {
                foreach ($resources as $resource) {
                    if (!is_null($resource->$column_id)) {
                        $incomplete_ancestor_IDs[$ancestor_class][] = $resource->$column_id;
                    }
                }
            }
        }

        do {
            foreach ($normal_hierarchy as $parent_ancestor_class => $child_ancestors) {
                if (count($child_ancestors) === 0) {
                    $target_IDs = $incomplete_ancestor_IDs[$parent_ancestor_class];
                    $target_IDs = array_unique($target_IDs);

                    $ancestor_model = model($parent_ancestor_class);
                    $ancestor_entities = $ancestor_model->selectUsingMultipleIDs($target_IDs);
                    $resolved_ancestors[$parent_ancestor_class] = array_merge(
                        $resolved_ancestors[$parent_ancestor_class],
                        $ancestor_entities
                    );
                    $indirect_ancestors = array_slice($ancestor_entities, 1);

                    $indirect_ancestor_information = $ancestor_model::identifyAncestors();
                    $ancestor_linked_columns = [];
                    foreach ($indirect_ancestor_information as $ancestor_class => $column_names) {
                        foreach ($column_names as $column_names) {
                            $ancestor_linked_columns[$column_names] = $ancestor_class;
                        }
                    }

                    foreach ($ancestor_entities as $ancestor_entity) {
                        foreach ($ancestor_linked_columns as $column_name => $ancestor_class) {
                            $incomplete_ancestor_IDs[$ancestor_class][]
                                = $ancestor_entity->$column_name;
                        }
                    }

                    unset($incomplete_ancestor_IDs[$parent_ancestor_class]);
                    unset($normal_hierarchy[$parent_ancestor_class]);
                    $normal_hierarchy = array_map(
                        function ($child_ancestors) use ($parent_ancestor_class) {
                            return array_diff($child_ancestors, [ $parent_ancestor_class ]);
                        },
                        $normal_hierarchy
                    );

                    break;
                }
            }
        } while (count($incomplete_ancestor_IDs) > 0);

        return array_values($resolved_ancestors);
    }

    protected static function identifyAncestors(): array
    {
        return [];
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
