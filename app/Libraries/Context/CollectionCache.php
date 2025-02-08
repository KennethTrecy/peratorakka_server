<?php

namespace App\Libraries\Context;

use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\AccountCollectionModel;
use App\Models\CollectionModel;
use Brick\Math\BigRational;

class CollectionCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::COLLECTION_CACHE;
    }

    protected static function getModel(): CollectionModel
    {
        return model(CollectionModel::class, false);
    }

    private array $collected_accounts = [];

    public function determineCollectionName(int $collection_id): ?string
    {
        return isset($this->resources[$collection_id])
            ? $this->resources[$collection_id]->name
            : null;
    }

    public function determineAccountIDs(int $collection_id): array
    {
        return isset($this->collected_accounts[$collection_id])
            ? $this->collected_accounts[$collection_id]
            : [];
    }

    public function loadCollectedAccounts(array $target_collection_IDs): void
    {
        $this->loadCollections($target_collection_IDs);

        if (count($target_collection_IDs) === 0 || $target_collection_IDs === null) {
            return;
        }

        $collected_accounts = model(AccountCollectionModel::class, false)
            ->whereIn("collection_id", $target_collection_IDs)
            ->findAll();

        foreach ($collected_accounts as $document) {
            $collection_id = $document->collection_id;
            $account_id = $document->account_id;
            if (!isset($this->collected_accounts[$collection_id])) {
                $this->collected_accounts[$collection_id] = [];
            }

            array_push($this->collected_accounts[$collection_id], $account_id);
        }
    }
}
