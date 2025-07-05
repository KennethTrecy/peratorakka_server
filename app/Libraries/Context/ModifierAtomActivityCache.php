<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\ModifierAtomActivityModel;

class ModifierAtomActivityCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::MODIFIER_ATOM_ACTIVITY_CACHE;
    }

    protected static function getModel(): ModifierAtomActivityModel
    {
        return model(ModifierAtomActivityModel::class, false);
    }

    public function extractAssociatedCashFlowActivityIDs(): array
    {
        $cash_flow_activity_IDs = [];
        foreach ($this->resources as $resource) {
            $cash_flow_activity_IDs[$resource->modifier_atom_id] = $resource->cash_flow_activity_id;
        }
        return $cash_flow_activity_IDs;
    }

    public function loadResourcesFromParentIDs(array $target_parent_IDs): void
    {
        $current_user = $this->context->user();

        $loaded_parent_IDs = array_map(
            fn ($resource) => $resource->modifier_atom_id,
            $this->resources
        );

        $missing_parent_IDs = array_values(array_diff(
            array_values($target_parent_IDs),
            array_values($loaded_parent_IDs)
        ));

        if (count($missing_parent_IDs) === 0) {
            return;
        }

        $scoped_model = static::getModel();
        $scoped_model = $scoped_model->limitSearchToUser($scoped_model, $current_user);
        $new_resources = $scoped_model
            ->whereIn("modifier_atom_id", array_unique($missing_parent_IDs))
            ->findAll();

        $this->resources = array_replace(
            $this->resources,
            Resource::key($new_resources, function ($resource) {
                return $resource->modifier_atom_id;
            })
        );
    }
}
