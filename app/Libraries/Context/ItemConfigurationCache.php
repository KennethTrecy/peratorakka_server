<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\ItemConfigurationModel;

class ItemConfigurationCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::ITEM_CONFIGURATION_CACHE;
    }

    protected static function getModel(): ItemConfigurationModel
    {
        return model(ItemConfigurationModel::class, false);
    }

    public function determineValuationMethodFromParent(int $account_id): ?string
    {
        foreach ($this->resources as $configuration) {
            if ($configuration->account_id === $account_id) {
                return $configuration->valuation_method;
            }
        }

        return null;
    }

    public function loadResourcesFromParentIDs(array $target_parent_IDs): void
    {
        $current_user = $this->context->user();

        $loaded_parent_IDs = array_map(fn ($resource) => $resource->account_id, $this->resources);

        $missing_parent_IDs = array_unique(array_values(array_diff(
            array_values($target_parent_IDs),
            array_values($loaded_parent_IDs)
        )));

        if (count($missing_parent_IDs) === 0) {
            return;
        }

        $scoped_model = static::getModel();
        $primary_key = $scoped_model->primaryKey;
        $scoped_model = $scoped_model->limitSearchToUser($scoped_model, $current_user);
        $new_resources = $scoped_model
            ->whereIn("account_id", $missing_parent_IDs)
            ->findAll();

        $this->resources = array_replace(
            $this->resources,
            Resource::key($new_resources, function ($resource) use ($primary_key) {
                return $resource->$primary_key;
            })
        );
    }
}
