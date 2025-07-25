<?php

namespace App\Database\Migrations;

use App\Entities\Account;
use App\Entities\Currency;
use App\Entities\AccountCollection;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\AccountCollectionModel;
use App\Models\Deprecated\DeprecatedAccountModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Models\Deprecated\DeprecatedAccountCollectionModel;
use CodeIgniter\Database\Migration;

class UpgradeAccountCollections extends Migration
{
    public function up()
    {
        $keyed_old_parents = Resource::key(
            model(DeprecatedAccountModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_parents = Resource::key(
            model(AccountModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
        );

        $old_entities = model(DeprecatedAccountCollectionModel::class, false)->findAll();
        $new_entities = [];
        foreach ($old_entities as $old_entity) {
            $new_entity = new AccountCollection();
            $old_account_id = $old_entity->account_id;
            $old_collection_id = $old_entity->collection_id;
            $old_account = $keyed_old_parents[$old_account_id];

            $new_account_id = $keyed_new_parents[
                $keyed_old_parents[$old_account_id]->name
                .$keyed_old_parents[$old_account_id]->created_at->toDateTimeString()
            ]->id;

            $new_entity->fill([
                "collection_id" => $old_collection_id,
                "account_id" => $new_account_id
            ]);

            array_push($new_entities, $new_entity);
        }

        if (count($new_entities) > 0) {
            model(AccountCollectionModel::class, false)->insertBatch($new_entities);
        }
    }

    public function down()
    {
        model(AccountCollectionModel::class)->where("account_id !=", 0)->delete();
    }
}
