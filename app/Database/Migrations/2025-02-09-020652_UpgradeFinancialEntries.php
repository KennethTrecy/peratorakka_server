<?php

namespace App\Database\Migrations;

use App\Entities\Account;
use App\Entities\Currency;
use App\Entities\FinancialEntry;
use App\Entities\FinancialEntryAtom;
use App\Entities\Modifier;
use App\Entities\ModifierAtom;
use App\Entities\ModifierAtomActivity;
use App\Entities\PrecisionFormat;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\Deprecated\DeprecatedAccountModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Models\Deprecated\DeprecatedFinancialEntryModel;
use App\Models\Deprecated\DeprecatedModifierModel;
use App\Models\FinancialEntryAtomModel;
use App\Models\FinancialEntryModel;
use App\Models\ModifierAtomActivityModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeFinancialEntries extends Migration
{
    public function up()
    {
        $keyed_old_grandgrandparents = Resource::key(
            model(DeprecatedCurrencyModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_old_grandparents = Resource::key(
            model(DeprecatedAccountModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_old_parents = Resource::key(
            model(DeprecatedModifierModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_grandparents = Resource::key(
            model(AccountModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
        );
        $keyed_new_parents = Resource::key(
            model(ModifierModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
        );
        $modifier_atoms = Resource::key(
            model(ModifierAtomModel::class, false)->findAll(),
            fn ($entity) => $entity->modifier_id.$entity->account_id
        );

        $offset = 0;
        $count_per_batch = 2500;

        do {
            $old_entities = model(DeprecatedFinancialEntryModel::class, false)
                ->withDeleted()
                ->findAll($count_per_batch, $offset);

            $new_entities = [];
            foreach ($old_entities as $old_entity) {
                $new_entity = new FinancialEntry();
                $old_modifier_id = $old_entity->modifier_id;
                $old_modifier = $keyed_old_parents[$old_modifier_id];
                $new_modifier = $keyed_new_parents[
                    $old_modifier->name.$old_modifier->created_at->toDateTimeString()
                ];

                $new_entity->fill([
                    "modifier_id" => $new_modifier->id,
                    "transacted_at" => $old_entity->transacted_at->toDateTimeString(),
                    "remarks" => $old_entity->remarks,
                    "created_at" => $old_entity->created_at,
                    "updated_at" => $old_entity->updated_at
                ]);

                array_push($new_entities, $new_entity);
            }

            if (count($new_entities) > 0) {
                model(FinancialEntryModel::class, false)->insertBatch($new_entities);

                $keyed_financial_entries_by_remarks_and_transacted_at = Resource::key(
                    model(FinancialEntryModel::class, false)->withDeleted()->findAll(),
                    fn ($entity) => (
                        $entity->remarks
                        .$entity->transacted_at->toDateTimeString()
                        .$entity->created_at->toDateTimeString()
                        .$entity->updated_at->toDateTimeString()
                    )
                );

                $new_child_entities = [];

                foreach ($old_entities as $old_entity) {
                    $old_modifier_id = $old_entity->modifier_id;
                    $old_modifier = $keyed_old_parents[$old_modifier_id];

                    $new_financial_entry = $keyed_financial_entries_by_remarks_and_transacted_at[
                        $old_entity->remarks
                        .$old_entity->transacted_at->toDateTimeString()
                        .$old_entity->created_at->toDateTimeString()
                        .$old_entity->updated_at->toDateTimeString()
                    ];
                    $new_financial_entry_id = $new_financial_entry->id;
                    $new_modifier_id = $new_financial_entry->modifier_id;

                    $new_debit_account_id = $keyed_new_grandparents[
                        $keyed_old_grandparents[
                            $old_modifier->debit_account_id
                        ]->name.$keyed_old_grandparents[
                            $old_modifier->debit_account_id
                        ]->created_at->toDateTimeString()
                    ]->id;
                    $new_modifier_atom_id = $modifier_atoms[
                        $new_modifier_id.$new_debit_account_id
                    ]->id;
                    $new_entity = new FinancialEntryAtom();
                    $new_entity->fill([
                        "financial_entry_id" => $new_financial_entry_id,
                        "modifier_atom_id" => $new_modifier_atom_id,
                        "numerical_value" => $old_entity->debit_amount->simplified()
                    ]);

                    array_push($new_child_entities, $new_entity);

                    $new_credit_account_id = $keyed_new_grandparents[
                        $keyed_old_grandparents[
                            $old_modifier->credit_account_id
                        ]->name.$keyed_old_grandparents[
                            $old_modifier->credit_account_id
                        ]->created_at->toDateTimeString()
                    ]->id;
                    $new_modifier_atom_id = $modifier_atoms[
                        $new_modifier_id.$new_credit_account_id
                    ]->id;
                    $new_entity = new FinancialEntryAtom();
                    $new_entity->fill([
                        "financial_entry_id" => $new_financial_entry_id,
                        "modifier_atom_id" => $new_modifier_atom_id,
                        "numerical_value" => $old_entity->credit_amount->simplified()
                    ]);

                    array_push($new_child_entities, $new_entity);
                }

                if (count($new_child_entities) > 0) {
                    model(FinancialEntryAtomModel::class, false)
                        ->insertBatch($new_child_entities);
                }
            }

            $offset += $count_per_batch;
        } while (count($old_entities) > 0);
    }

    public function down()
    {
        model(FinancialEntryAtomModel::class, false)->where("id !=", 0)->delete();
        model(FinancialEntryModel::class, false)->where("id !=", 0)->delete();
    }
}
