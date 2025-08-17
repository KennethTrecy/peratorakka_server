<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\ModifierAtomModel;

class ModifierAtomCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::MODIFIER_ATOM_CACHE;
    }

    protected static function getModel(): ModifierAtomModel
    {
        return model(ModifierAtomModel::class, false);
    }

    public function determineModifierAtomAccountID(int $modifier_atom_id): ?string
    {
        return isset($this->resources[$modifier_atom_id])
            ? $this->resources[$modifier_atom_id]->account_id
            : null;
    }

    public function determineModifierAtomKind(int $modifier_atom_id): ?string
    {
        return isset($this->resources[$modifier_atom_id])
            ? $this->resources[$modifier_atom_id]->kind
            : null;
    }

    public function extractAssociatedAccountIDs(): array
    {
        $account_IDs = [];
        foreach ($this->resources as $resource) {
            $account_IDs[$resource->id] = $resource->account_id;
        }
        return $account_IDs;
    }

    public function loadResourcesFromParentIDs(array $target_parent_IDs): void
    {
        $current_user = $this->context->user();

        $loaded_parent_IDs = array_map(fn ($resource) => $resource->modifier_id, $this->resources);

        $missing_parent_IDs = array_values(array_diff(
            array_values($target_parent_IDs),
            array_values($loaded_parent_IDs)
        ));

        if (count($missing_parent_IDs) === 0) {
            return;
        }

        $scoped_model = static::getModel();
        $primary_key = $scoped_model->primaryKey;
        $scoped_model = $scoped_model->limitSearchToUser($scoped_model, $current_user);
        $new_resources = $scoped_model
            ->whereIn("modifier_id", array_unique($missing_parent_IDs))
            ->withDeleted()
            ->findAll();

        $this->resources = array_replace(
            $this->resources,
            Resource::key($new_resources, function ($resource) use ($primary_key) {
                return $resource->$primary_key;
            })
        );
    }

    public function getLoadedResourcesFromParentIDs(array $target_parent_IDs): array
    {
        return array_filter(
            $this->resources,
            fn ($resource) => in_array($resource->modifier_id, $target_parent_IDs)
        );
    }
}
