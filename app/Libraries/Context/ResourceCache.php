<?php

namespace App\Libraries\Context;

use App\Contracts\OwnedResource;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;

abstract class ResourceCache extends SingletonCache
{
    abstract protected static function getModel(): OwnedResource;

    protected array $resources = [];

    public function countLoadedResources(): int
    {
        return count($this->resources);
    }

    public function getLoadedResource($account_id): ?OwnedResource {
        return $this->resources[$account_id] ?? null;
    }

    public function loadResources(array $target_resource_IDs): void
    {
        $current_user = auth()->user();

        $missing_resource_IDs = array_values(array_diff(
            array_values($target_resource_IDs),
            array_values(array_keys($this->resources))
        ));

        if (count($missing_resource_IDs) === 0) {
            return;
        }

        $scoped_model = static::getModel();
        $scoped_model = $scoped_model->limitSearchToUser($scoped_model, $current_user);
        $new_resources = $scoped_model
            ->whereIn($scoped_model->primaryKey, array_unique($missing_resource_IDs))
            ->withDeleted()
            ->findAll();

        $this->resources = array_replace(
            $this->resources,
            Resource::key($new_resources, function ($resource) {
                return $resource->id;
            })
        );
    }

    public function addPreloadedResources(array $resources): void
    {
        $this->resources = array_replace(
            $this->resources,
            Resource::key($new_resources, function ($resource) {
                return $resource->id;
            })
        );
    }
}
