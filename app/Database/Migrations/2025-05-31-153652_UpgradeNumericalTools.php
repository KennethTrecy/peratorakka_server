<?php

namespace App\Database\Migrations;

use App\Casts\Deprecated\NumericalToolConfiguration as DeprecatedNumericalToolConfigurationCast;
use App\Casts\NumericalToolConfiguration;
use App\Entities\NumericalTool;
use App\Entities\Currency;
use App\Entities\PrecisionFormat;
use App\Libraries\Resource;
use App\Models\NumericalToolModel;
use App\Models\CurrencyModel;
use App\Models\FormulaModel;
use App\Models\Deprecated\DeprecatedNumericalToolModel;
use App\Models\Deprecated\DeprecatedFormulaModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Libraries\NumericalToolConfiguration\Deprecated\DeprecatedCollectionSource;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeNumericalTools extends Migration
{
    public function up()
    {
        $keyed_old_parents = Resource::key(
            model(DeprecatedCurrencyModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_parents = Resource::key(
            model(CurrencyModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->created_at->toDateTimeString()
        );
        $keyed_old_formulae = Resource::key(
            model(DeprecatedFormulaModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_formulae = Resource::key(
            model(FormulaModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->created_at->toDateTimeString()
        );
        $old_entities = model(DeprecatedNumericalToolModel::class)
            ->orderBy("created_at", "ASC")
            ->withDeleted()
            ->findAll();
        $deprecated_collection_source_type = DeprecatedCollectionSource::sourceType();

        $new_entities = [];
        foreach ($old_entities as $old_entity) {
            $new_entity = new NumericalTool();
            $sources = json_decode(DeprecatedNumericalToolConfigurationCast::set(
                $old_entity->configuration
            ), true)["sources"];
            [
                $old_currency_id,
                $exchange_rate_basis
            ] = $sources[0]["type"] === $deprecated_collection_source_type
                ? [ $sources[0]["currency_id"], $sources[0]["exchange_rate_basis"] ]
                : (function () use ($sources, $keyed_old_formulae) {
                    $old_formula = $keyed_old_formulae[$sources[0]["formula_id"]];

                    return [ $old_formula->currency_id, $old_formula->exchange_rate_basis ];
                })();
            $new_parent_id = $keyed_new_parents[
                $keyed_old_parents[$old_currency_id]->created_at->toDateTimeString()
            ]->id;

            $new_entity->fill([
                "currency_id" => $new_parent_id,
                "exchange_rate_basis" => $exchange_rate_basis,
                "name" => $old_entity->name,
                "kind" => $old_entity->kind,
                "recurrence" => $old_entity->recurrence,
                "recency" => $old_entity->recency,
                "order" => $old_entity->order,
                "notes" => $old_entity->notes,
                "description" => $old_entity->description,
                "configuration" => NumericalToolConfiguration::get(json_encode([
                    "sources" => array_map(
                        function ($source) use (
                            $deprecated_collection_source_type,
                            $keyed_old_formulae,
                            $keyed_new_formulae
                        ) {
                            return $source["type"] === $deprecated_collection_source_type
                                ? [
                                    "type" => CollectionSource::sourceType(),
                                    "collection_id" => $source["collection_id"],
                                    "stage_basis" => $source["stage_basis"],
                                    "side_basis" => $source["side_basis"],
                                    "must_show_individual_amounts" => $source[
                                        "must_show_individual_amounts"
                                    ],
                                    "must_show_collective_sum" => $source[
                                        "must_show_collective_sum"
                                    ],
                                    "must_show_collective_average" => $source[
                                        "must_show_collective_average"
                                    ]
                                ]
                                : [
                                    "type" => FormulaSource::sourceType(),
                                    "formula_id" => $keyed_new_formulae[
                                        $keyed_old_formulae[
                                            $source["formula_id"]
                                        ]->created_at->toDateTimeString()
                                    ]->id
                                ];
                        },
                        $sources
                    )
                ])),
                "created_at" => $old_entity->created_at,
                "deleted_at" => $old_entity->deleted_at
            ]);

            array_push($new_entities, $new_entity);
        }

        if (count($new_entities) > 0) {
            model(NumericalToolModel::class)->insertBatch($new_entities);
        }
    }

    public function down()
    {
        model(NumericalToolModel::class)->where("id !=", 0)->delete();
    }
}
