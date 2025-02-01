<?php

namespace App\Database\Migrations;

use App\Entities\Account;
use App\Entities\Currency;
use App\Entities\PrecisionFormat;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\Deprecated\DeprecatedAccountModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeAccounts extends Migration
{
    public function up()
    {
        $keyed_old_parents = Resource::key(
            model(DeprecatedCurrencyModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_parents = Resource::key(
            model(CurrencyModel::class)->findAll(),
            fn ($entity) => $entity->created_at->toDateTimeString()
        );
        $old_entities = model(DeprecatedAccountModel::class)
            ->orderBy("created_at", "ASC")
            ->withDeleted()
            ->findAll();
        $new_entities = [];
        foreach ($old_entities as $old_entity) {
            $new_entity = new Account();
            $new_parent_id = $keyed_new_parents[
                $keyed_old_parents[$old_entity->currency_id]->created_at->toDateTimeString()
            ]->id;
            $new_entity->fill([
                "currency_id" => $new_parent_id,
                "name" => $old_entity->name,
                "description" => $old_entity->description,
                "kind" => $old_entity->kind,
                "created_at" => $old_entity->created_at,
                "deleted_at" => $old_entity->deleted_at
            ]);

            array_push($new_entities, $new_entity);
        }

        if (count($new_entities) > 0) {
            model(AccountModel::class)->insertBatch($new_entities);
        }
    }

    public function down()
    {
        model(AccountModel::class)->where("id !=", 0)->delete();
    }
}
