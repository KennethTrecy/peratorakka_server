<?php

namespace App\Models;

use App\Contracts\OwnedResource;
use App\Exceptions\UnprocessableRequest;
use CodeIgniter\Model;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Fabricator;
use CodeIgniter\Test\Interfaces\FabricatorModel;

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

        array_push($options, [ $this->primaryKey, "ASC" ]);

        foreach ($options as $option) {
            [ $criteria, $order ] = $option;
            $order = in_array($order, array_keys($order_translation))
                ? $order_translation[$order]
                : "ASC";

            if (in_array($criteria, $this->sortable_fields) || $criteria === $this->primaryKey) {
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

    public static function selectAncestorsWithResolvedResources(
        array $resources,
        array $relationship = ["*"]
    ): array {
        $direct_ancestor_information = static::identifyAncestors();
        $newly_discovered_ancestors = array_keys($direct_ancestor_information);
        // Descendants are the keys and parent are the values
        $inverse_hierarchy = array_map(
            function () { return []; },
            array_flip($newly_discovered_ancestors)
        );
        while (count($newly_discovered_ancestors) > 0) {
            $newly_discovered_ancestor = array_shift($newly_discovered_ancestors);

            $ancestor_model = model($newly_discovered_ancestor, false);
            $indirect_ancestor_information = $ancestor_model::identifyAncestors();
            $parent_ancestors = array_keys($indirect_ancestor_information);
            $newly_discovered_ancestors = array_merge(
                $newly_discovered_ancestors,
                array_diff($parent_ancestors, array_keys($inverse_hierarchy))
            );
            $inverse_hierarchy[$newly_discovered_ancestor] = $parent_ancestors;
        }

        // Parents are the keys and descendants are the values.
        // If entity belonged in normal hierarchy, its children are not yet loaded.
        $normal_hierarchy = array_map(
            function () { return []; },
            array_flip(array_keys($inverse_hierarchy))
        );
        foreach ($inverse_hierarchy as $child_ancestor => $parent_ancestors) {
            foreach ($parent_ancestors as $parent_ancestor) {
                $normal_hierarchy[$parent_ancestor][] = $child_ancestor;
            }
        }

        // Records IDs that have been found
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

        // Records the parents that have been found
        $resolved_ancestors = array_map(function () { return []; }, $inverse_hierarchy);
        // Loops until no ancestor IDs are waiting to be loaded
        do {
            // Loops until a parents have been loaded
            foreach ($normal_hierarchy as $parent_ancestor_class => $child_ancestors) {
                // Check if the current entity has no pending child ancestors
                if (count($child_ancestors) === 0) {
                    $target_IDs = $incomplete_ancestor_IDs[$parent_ancestor_class];
                    $target_IDs = array_unique($target_IDs);

                    // Load pending IDs of the parent
                    $ancestor_model = model($parent_ancestor_class, false);
                    $ancestor_entities = $ancestor_model->selectUsingMultipleIDs($target_IDs);
                    $resolved_ancestors[$parent_ancestor_class] = array_merge(
                        $resolved_ancestors[$parent_ancestor_class],
                        $ancestor_entities
                    );

                    // Load grandparent information
                    $indirect_ancestor_information = $ancestor_model::identifyAncestors();
                    $ancestor_linked_columns = [];
                    foreach ($indirect_ancestor_information as $ancestor_class => $column_names) {
                        foreach ($column_names as $column_names) {
                            $ancestor_linked_columns[$column_names] = $ancestor_class;
                        }
                    }

                    // Extract grandparent IDs of loaded parents and include to pending IDs
                    foreach ($ancestor_entities as $ancestor_entity) {
                        foreach ($ancestor_linked_columns as $column_name => $ancestor_class) {
                            $incomplete_ancestor_IDs[$ancestor_class][]
                                = $ancestor_entity->$column_name;
                        }
                    }

                    // Remove pending IDs as they are now loaded
                    unset($incomplete_ancestor_IDs[$parent_ancestor_class]);
                    // Remove parent entity
                    unset($normal_hierarchy[$parent_ancestor_class]);
                    // Remove parent entity that is a child in other entities
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

    public static function createTestResources(
        int $user_id,
        int $count_per_user,
        array $options
    ): array {
        return static::createOrMakeTestResources($user_id, $count_per_user, "create", $options);
    }

    public static function createTestResource(int $user_id, array $options): array
    {
        $resources = static::createTestResources($user_id, 1, $options);
        $last_resource_index = count($resources) - 1;
        $resources[$last_resource_index] = count($resources[$last_resource_index]) === 1
            ? $resources[$last_resource_index][0]
            : $resources[$last_resource_index];

        return $resources;
    }

    public static function makeTestResources(
        int $user_id,
        int $count_per_user,
        array $options
    ): array {
        return static::createOrMakeTestResources($user_id, $count_per_user, "make", $options);
    }

    public static function makeTestResource(int $user_id, array $options): array
    {
        $resources = static::makeTestResources($user_id, 1, $options);
        $last_resource_index = count($resources) - 1;
        $resources[$last_resource_index] = count($resources[$last_resource_index]) === 1
            ? $resources[$last_resource_index][0]
            : $resources[$last_resource_index];

        return $resources;
    }

    public static function createAndMakeTestResources(int $user_id, array $options): array
    {
        $ancestor_data = isset($options["ancestor_data"])
            ? $options["ancestor_data"]
            : static::createAncestorResources($user_id, $options);
        $options["ancestor_data"] = $ancestor_data;
        $original_overrides = isset($options["overrides"]) ? $options["overrides"] : [];

        $created_resources = static::createTestResources($user_id, 1, $options);

        $made_options = [ ...$options ];
        if (isset($options["make_overrides"])) {
            $made_options["overrides"] = array_merge(
                $original_overrides,
                $options["make_overrides"]
            );
        }
        $made_resources = static::makeTestResources($user_id, 1, $made_options);
        $ancestor_resources = $ancestor_data[0];
        $last_index = count($ancestor_resources);

        return array_merge(
            $ancestor_resources,
            $created_resources[$last_index],
            $made_resources[$last_index]
        );
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        $ancestor_resources = [];
        $parent_links = static::permutateParentLinks([
            "user_id" => [ $user_id ]
        ], $options);

        return [
            $ancestor_resources,
            $parent_links
        ];
    }

    protected static function permutateParentLinks(
        array $parent_links,
        array $options
    ): array {
        $permutated_links = [];

        foreach ($parent_links as $parent_link_column_name => $parent_link_IDs) {
            if (count($parent_link_IDs) === 0) {
                continue;
            }

            if (count($permutated_links) === 0) {
                $permutated_links = array_map(fn ($parent_link_id) => [
                    $parent_link_column_name => $parent_link_id
                ], $parent_link_IDs);
            } else {
                $permutated_links = array_reduce(
                    $permutated_links,
                    fn ($previous_links, $permutated_link) => array_merge(
                        $previous_links,
                        array_map(fn ($parent_link_id) => [
                            ...$permutated_link,
                            $parent_link_column_name => $parent_link_id
                        ], $parent_link_IDs)
                    ),
                    []
                );
            }
        }

        return $permutated_links;
    }

    protected static function createOrMakeTestResources(
        int $user_id,
        int $count_per_parent,
        string $fabricator_generation_method,
        array $options
    ): array {
        $resources = [];
        [
            $ancestor_resources,
            $parent_links
        ] = isset($options["ancestor_data"])
            ? $options["ancestor_data"]
            : static::createAncestorResources($user_id, $options);

        $fabricator = new Fabricator(static::class);
        foreach ($parent_links as $parent_link) {
            $overrides = $parent_link;
            if (isset($options["overrides"])) {
                $overrides = array_merge($overrides, $options["overrides"]);
            }
            $fabricator->setOverrides($overrides);

            if ($count_per_parent === 1) {
                array_push($resources, $fabricator->$fabricator_generation_method());
            } else {
                $resources = array_merge(
                    $resources,
                    $fabricator->$fabricator_generation_method($count_per_parent)
                );
            }
        }

        return array_merge($ancestor_resources, [ $resources ]);
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
