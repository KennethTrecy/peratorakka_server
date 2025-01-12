<?php

namespace App\Libraries\Context\TimeGroupManager;

use App\Libraries\Context\ContextKeys;
use App\Libraries\Context;
use App\Libraries\Resource;
use App\Models\CollectionModel;
use Brick\Math\BigRational;

class CollectionCache {
    public readonly Context $context;
    private array $collections = [];

    public function __construct(Context $context)
    {
        $this->context = $context;

        $this->context->setVariable(ContextKeys::COLLECTION_CACHE, $this);
    }

    public function determineCollectionName(int $collection_id): ?string {
        return isset($this->collections[$collection_id])
            ? $this->collections[$collection_id]->name
            : null;
    }

    public function loadCollections(array $target_collection_IDs): void {
        $missing_collection_IDs = array_diff(
            $target_collection_IDs,
            array_keys($this->collections)
        );

        if (count($missing_collection_IDs) === 0) return;

        $new_collections = model(CollectionModel::class, false)
            ->whereIn("id", array_unique($missing_collection_IDs))
            ->findAll();

        $this->collections = array_replace(
            $this->collections,
            Resource::key($new_collections, function ($collection) {
                return $collection->id;
            })
        );
    }
}
