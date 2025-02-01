<?php

namespace App\Database\Migrations;

use App\Entities\Account;
use App\Entities\Currency;
use App\Entities\Modifier;
use App\Entities\ModifierAtom;
use App\Entities\ModifierAtomActivity;
use App\Entities\PrecisionFormat;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\Deprecated\DeprecatedAccountModel;
use App\Models\Deprecated\DeprecatedCurrencyModel;
use App\Models\Deprecated\DeprecatedModifierModel;
use App\Models\ModifierAtomActivityModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeModifiers extends Migration
{
    public function up()
    {
        $keyed_old_grandparents = Resource::key(
            model(DeprecatedCurrencyModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_old_parents = Resource::key(
            model(DeprecatedAccountModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_parents = Resource::key(
            model(AccountModel::class)->withDeleted()->findAll(),
            fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
        );

        $old_entities = model(DeprecatedModifierModel::class, false)
            ->orderBy("created_at", "ASC")
            ->withDeleted()
            ->findAll();
        $known_unique_data = [];
        $new_entities = [];
        foreach ($old_entities as $old_entity) {
            $new_entity = new Modifier();
            $old_account_id = $old_entity->credit_account_id;
            $old_currency_id = $keyed_old_parents[$old_account_id]->currency_id;
            $user_id = $keyed_old_grandparents[$old_currency_id]->user_id;
            $name = $old_entity->name;
            $i = 0;
            $generated_name = $name;
            $unique_generated_name = $user_id.$generated_name;

            while (in_array($unique_generated_name, $known_unique_data)) {
                $generated_name = $name." ".(++$i);
                $unique_generated_name = $user_id.$generated_name;
            }

            if ($old_entity->name !== $generated_name) {
                $old_entity->name = $generated_name;
                $old_entity->name->save();
            }
            array_push($known_unique_data, $unique_generated_name);
            $new_entity->fill([
                "user_id" => $user_id,
                "name" => $generated_name,
                "description" => $old_entity->description,
                "kind" => $old_entity->kind,
                "action" => $keyed_old_parents[$old_entity->debit_account_id]->currency_id
                    !== $keyed_old_parents[$old_entity->credit_account_id]->currency_id
                        ? EXCHANGE_MODIFIER_ACTION
                        : $old_entity->action,
                "created_at" => $old_entity->created_at->toDateTimeString(),
                "deleted_at" => is_null($old_entity->deleted_at)
                    ? null
                    : $old_entity->deleted_at->toDateTimeString()
            ]);

            array_push($new_entities, $new_entity);
        }

        if (count($new_entities) > 0) {
            model(ModifierModel::class)->insertBatch($new_entities);

            $keyed_modifiers_by_name_and_date = Resource::key(
                model(ModifierModel::class)->withDeleted()->findAll(),
                fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
            );
            $new_child_entities = [];

            foreach ($old_entities as $old_entity) {
                $modifier_id = $keyed_modifiers_by_name_and_date[
                    $old_entity->name.$old_entity->created_at->toDateTimeString()
                ]->id;

                $debit_account_id = $keyed_new_parents[
                    $keyed_old_parents[
                        $old_entity->debit_account_id
                    ]->name.$keyed_old_parents[
                        $old_entity->debit_account_id
                    ]->created_at->toDateTimeString()
                ]->id;
                $new_entity = new ModifierAtom();
                $new_entity->fill([
                    "modifier_id" => $modifier_id,
                    "account_id" => $debit_account_id,
                    "kind" => DEBIT_MODIFIER_ATOM_KIND
                ]);

                array_push($new_child_entities, $new_entity);

                $credit_account_id = $keyed_new_parents[
                    $keyed_old_parents[
                        $old_entity->credit_account_id
                    ]->name.$keyed_old_parents[
                        $old_entity->credit_account_id
                    ]->created_at->toDateTimeString()
                ]->id;
                $new_entity = new ModifierAtom();
                $new_entity->fill([
                    "modifier_id" => $modifier_id,
                    "account_id" => $credit_account_id,
                    "kind" => CREDIT_MODIFIER_ATOM_KIND
                ]);

                array_push($new_child_entities, $new_entity);
            }

            if (count($new_child_entities) > 0) {
                model(ModifierAtomModel::class)->insertBatch($new_child_entities);

                $keyed_modifiers_by_id = Resource::key(
                    array_values($keyed_modifiers_by_name_and_date),
                    fn ($entity) => $entity->id
                );
                $keyed_modifier_atoms = Resource::key(
                    model(ModifierAtomModel::class)->findAll(),
                    fn ($entity) => (
                        $keyed_modifiers_by_id[$entity->modifier_id]->name
                        .$keyed_modifiers_by_id[$entity->modifier_id]->created_at->toDateTimeString()
                        ."_"
                        .$entity->account_id
                    )
                );
                $new_grandchild_entities = [];

                foreach ($old_entities as $old_entity) {
                    if (!is_null($old_entity->debit_cash_flow_activity_id)) {
                        $account_id = $keyed_new_parents[
                            $keyed_old_parents[
                                $old_entity->debit_account_id
                            ]->name.$keyed_old_parents[
                                $old_entity->debit_account_id
                            ]->created_at->toDateTimeString()
                        ]->id;

                        $modifier_atom_id = $keyed_modifier_atoms[
                            $old_entity->name
                            .$old_entity->created_at->toDateTimeString()
                            ."_"
                            .$account_id
                        ]->id;

                        $new_entity = new ModifierAtomActivity();
                        $new_entity->fill([
                            "modifier_atom_id" => $modifier_atom_id,
                            "cash_flow_activity_id" => $old_entity->debit_cash_flow_activity_id
                        ]);

                        array_push($new_grandchild_entities, $new_entity);
                    }

                    if (!is_null($old_entity->credit_cash_flow_activity_id)) {
                        $account_id = $keyed_new_parents[
                            $keyed_old_parents[
                                $old_entity->credit_account_id
                            ]->name.$keyed_old_parents[
                                $old_entity->credit_account_id
                            ]->created_at->toDateTimeString()
                        ]->id;

                        $modifier_atom_id = $keyed_modifier_atoms[
                            $old_entity->name
                            .$old_entity->created_at->toDateTimeString()
                            ."_"
                            .$account_id
                        ]->id;

                        $new_entity = new ModifierAtomActivity();
                        $new_entity->fill([
                            "modifier_atom_id" => $modifier_atom_id,
                            "cash_flow_activity_id" => $old_entity->credit_cash_flow_activity_id
                        ]);

                        array_push($new_grandchild_entities, $new_entity);
                    }
                }

                if (count($new_grandchild_entities) > 0) {
                    model(ModifierAtomActivityModel::class)->insertBatch($new_grandchild_entities);
                }
            }
        }
    }

    public function down()
    {
        model(ModifierAtomActivityModel::class)->where("modifier_atom_id !=", 0)->delete();
        model(ModifierAtomModel::class)->where("modifier_id !=", 0)->delete();
        model(ModifierModel::class)->where("id !=", 0)->delete();
    }
}
