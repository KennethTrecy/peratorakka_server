<?php

namespace App\Database\Migrations;

use App\Entities\Account;
use App\Entities\FrozenAccount;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\Deprecated\DeprecatedAccountModel;
use App\Models\Deprecated\DeprecatedFlowCalculationModel;
use App\Models\Deprecated\DeprecatedSummaryCalculationModel;
use App\Models\FrozenAccountModel;
use App\Models\FrozenPeriodModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use App\Models\RealFlowCalculationModel;
use App\Models\RealUnadjustedSummaryCalculationModel;
use CodeIgniter\Database\Migration;
use CodeIgniter\Shield\Models\UserModel;

class UpgradeFrozenCalculations extends Migration
{
    public function up()
    {
        $keyed_old_parents = Resource::key(
            model(DeprecatedAccountModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_frozen_period = Resource::key(
            model(FrozenPeriodModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->id
        );
        $keyed_new_parents = Resource::key(
            model(AccountModel::class, false)->withDeleted()->findAll(),
            fn ($entity) => $entity->name.$entity->created_at->toDateTimeString()
        );

        $offset = 0;
        $count_per_batch = 2500;
        $hashed_frozen_accounts = [];

        do {
            $old_entities = model(DeprecatedFlowCalculationModel::class, false)
                ->withDeleted()
                ->findAll($count_per_batch, $offset);

            $unknown_hashed_frozen_accounts = [];
            $new_entities = [];
            foreach ($old_entities as $old_entity) {
                $old_account_id = $old_entity->account_id;
                $old_account = $keyed_old_parents[$old_account_id];
                $new_account = $keyed_new_parents[
                    $old_account->name.$old_account->created_at->toDateTimeString()
                ];

                $frozen_period_id = $old_entity->frozen_period_id;
                $frozen_period = $keyed_frozen_period[$frozen_period_id];
                $hash = FrozenAccountModel::generateAccountHash(
                    $frozen_period->started_at,
                    $frozen_period->finished_at,
                    $new_account->id
                );
                $frozen_account = $hashed_frozen_accounts[$hash]
                    ?? $unknown_hashed_frozen_accounts[$hash]
                    ?? null;
                if (is_null($frozen_account)) {
                    $frozen_account = new FrozenAccount([
                        "hash" => $hash,
                        "frozen_period_id" => $frozen_period->id,
                        "account_id" => $new_account->id
                    ]);
                    $unknown_hashed_frozen_accounts[$hash] = $frozen_account;
                }

                $new_entity = new RealFlowCalculation();
                $new_entity->fill([
                    "frozen_account_hash" => $frozen_account->hash,
                    "cash_flow_activity_id" => $old_entity->cash_flow_activity_id,
                    "net_amount" => $old_entity->net_amount
                ]);

                array_push($new_entities, $new_entity);
            }

            if (count($unknown_hashed_frozen_accounts) > 0) {
                model(FrozenAccountModel::class, false)
                    ->insertBatch($unknown_hashed_frozen_accounts);
                $hashed_frozen_accounts = array_merge(
                    $hashed_frozen_accounts,
                    $unknown_hashed_frozen_accounts
                );
            }
            if (count($new_entities) > 0) {
                model(RealFlowCalculationModel::class, false)->insertBatch($new_entities);
            }

            $offset += $count_per_batch;
        } while (count($old_entities) > 0);

        $offset = 0;
        do {
            $old_entities = model(DeprecatedSummaryCalculationModel::class, false)
                ->withDeleted()
                ->findAll($count_per_batch, $offset);

            $unknown_hashed_frozen_accounts = [];
            $new_unadjusted_entities = [];
            $new_adjusted_entities = [];
            foreach ($old_entities as $old_entity) {
                $old_account_id = $old_entity->account_id;
                $old_account = $keyed_old_parents[$old_account_id];
                $new_account = $keyed_new_parents[
                    $old_account->name.$old_account->created_at->toDateTimeString()
                ];

                $frozen_period_id = $old_entity->frozen_period_id;
                $frozen_period = $keyed_frozen_period[$frozen_period_id];
                $hash = FrozenAccountModel::generateAccountHash(
                    $frozen_period->started_at,
                    $frozen_period->finished_at,
                    $new_account->id
                );
                $frozen_account = $hashed_frozen_accounts[$hash]
                    ?? $unknown_hashed_frozen_accounts[$hash]
                    ?? null;
                if (is_null($frozen_account)) {
                    $frozen_account = new FrozenAccount([
                        "hash" => $hash,
                        "frozen_period_id" => $frozen_period->id,
                        "account_id" => $new_account->id
                    ]);
                    $unknown_hashed_frozen_accounts[$hash] = $frozen_account;
                }

                if (
                    !in_array($new_account->kind, TEMPORARY_ACCOUNT_KINDS)
                    && !(
                        $old_entity->opened_debit_amount->isZero()
                        && $old_entity->opened_credit_amount->isZero()
                    )
                ) {
                    $new_adjusted_entity = new RealAdjustedSummaryCalculation();
                    $new_adjusted_entity->fill([
                        "frozen_account_hash" => $frozen_account->hash,
                        "opened_amount" => in_array($new_account->kind, NORMAL_DEBIT_ACCOUNT_KINDS)
                            ? $old_entity->opened_debit_amount
                                ->minus($old_entity->opened_credit_amount)
                                ->simplified()
                            : $old_entity->opened_credit_amount
                                ->minus($old_entity->opened_debit_amount)
                                ->simplified(),
                        "closed_amount" => in_array($new_account->kind, NORMAL_DEBIT_ACCOUNT_KINDS)
                            ? $old_entity->closed_debit_amount
                                ->minus($old_entity->closed_credit_amount)
                                ->simplified()
                            : $old_entity->closed_credit_amount
                                ->minus($old_entity->closed_debit_amount)
                                ->simplified()
                    ]);

                    array_push($new_adjusted_entities, $new_adjusted_entity);
                }

                if (!(
                    $old_entity->unadjusted_debit_amount->isZero()
                    && $old_entity->unadjusted_credit_amount->isZero()
                )) {
                    $new_unadjusted_entity = new RealUnadjustedSummaryCalculation();
                    $new_unadjusted_entity->fill([
                        "frozen_account_hash" => $frozen_account->hash,
                        "debit_amount" => $old_entity->unadjusted_debit_amount->simplified(),
                        "credit_amount" => $old_entity->unadjusted_credit_amount->simplified()
                    ]);

                    array_push($new_unadjusted_entities, $new_unadjusted_entity);
                }
            }

            if (count($unknown_hashed_frozen_accounts) > 0) {
                model(FrozenAccountModel::class, false)
                    ->insertBatch($unknown_hashed_frozen_accounts);
                $hashed_frozen_accounts = array_merge(
                    $hashed_frozen_accounts,
                    $unknown_hashed_frozen_accounts
                );
            }
            if (count($new_adjusted_entities) > 0) {
                model(RealAdjustedSummaryCalculationModel::class, false)
                    ->insertBatch($new_adjusted_entities);
            }
            if (count($new_unadjusted_entities) > 0) {
                model(RealUnadjustedSummaryCalculationModel::class, false)
                    ->insertBatch($new_unadjusted_entities);
            }

            $offset += $count_per_batch;
        } while (count($old_entities) > 0);
    }

    public function down()
    {
        model(FrozenAccountModel::class, false)->where("hash !=", "")->delete();
        model(RealFlowCalculationModel::class, false)
            ->where("frozen_account_hash !=", "")->delete();
        model(RealAdjustedSummaryCalculationModel::class, false)
            ->where("frozen_account_hash !=", "")->delete();
        model(RealUnadjustedSummaryCalculationModel::class, false)
            ->where("frozen_account_hash !=", "")->delete();
    }
}
