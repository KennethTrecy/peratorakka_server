<?php

namespace App\Libraries\Context;

use App\Contracts\OwnedResource;
use App\Entities\BaseResourceEntity;
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

    public function getLoadedResource($id): BaseResourceEntity
    {
        return $this->resources[$id];
    }

    public function getLoadedResources($IDs): array
    {
        $scoped_model = static::getModel();
        $primary_key = $scoped_model->primaryKey;
        $unique_IDs = array_unique($IDs);
        $loaded_resources = [];

        foreach ($unique_IDs as $ID) {
            if (isset($this->resources[$ID])) {
                array_push($loaded_resources, $this->resources[$ID]);
            }
        }

        return $loaded_resources;
    }

    public function loadResources(array $target_resource_IDs): void
    {
        $current_user = $this->context->user();

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

        $this->addPreloadedResources($new_resources);
    }

    public function addPreloadedResources(array $resources): void
    {
        $scoped_model = static::getModel();
        $primary_key = $scoped_model->primaryKey;
        $this->resources = array_replace(
            $this->resources,
            Resource::key($resources, fn ($resource) => $resource->$primary_key)
        );
    }
}
