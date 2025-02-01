<?php

namespace App\Database\Migrations;

use App\Entities\Currency;
use App\Entities\PrecisionFormat;
use App\Libraries\Resource;
use App\Models\CurrencyModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeCurrencies extends Migration
{
    public function up()
    {
        $users = model(UserModel::class)->findAll();
        $precision_formats = [];

        foreach ($users as $user) {
            $precision_format_entity = new PrecisionFormat();
            $precision_format_entity->fill([
                "user_id" => $user->id,
                "name" => "Fiat Currency Precision",
                "minimum_presentational_precision" => 0,
                "maximum_presentational_precision" => 2
            ]);

            array_push($precision_formats, $precision_format_entity);
        }

        if (count($precision_formats) > 0) {
            model(PrecisionFormatModel::class)->insertBatch($precision_formats);
        }

        $keyed_precision_formats = Resource::key(
            model(PrecisionFormatModel::class)->findAll(),
            fn ($entity) => $entity->user_id
        );

        $old_entities = model(DeprecatedCurrencyModel::class)
            ->orderBy("created_at", "ASC")
            ->withDeleted()
            ->findAll();
        $new_entities = [];
        foreach ($old_entities as $old_entity) {
            $new_entity = new Currency();
            $new_entity->fill([
                "precision_format_id" => $keyed_precision_formats[$old_entity->user_id]->id,
                "code" => $old_entity->code,
                "name" => $old_entity->name,
                "created_at" => $old_entity->created_at,
                "deleted_at" => $old_entity->deleted_at
            ]);

            array_push($new_entities, $new_entity);
        }

        if (count($new_entities) > 0) {
            model(CurrencyModel::class)->insertBatch($new_entities);
        }
    }

    public function down()
    {
        model(PrecisionFormatModel::class)->where("id !=", 0)->delete();
    }
}
