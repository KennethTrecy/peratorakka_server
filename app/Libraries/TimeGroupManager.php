<?php

namespace App\Libraries;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FrozenAccount;
use App\Entities\FrozenPeriod;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CollectionCache;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\CurrencyCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\Context\FrozenAccountCache;
use App\Libraries\Resource;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Models\FrozenAccountModel;
use App\Models\FrozenPeriodModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use App\Models\RealFlowCalculationModel;
use App\Models\RealUnadjustedSummaryCalculationModel;
use CodeIgniter\I18n\Time;

/**
 * Manager for different groups of frozen periods.
 *
 * Assumes exchange rates and destination currency ID was already loaded.
 */
class TimeGroupManager
{
    public readonly Context $context;

    private readonly array $time_groups;
    private readonly ExchangeRateCache $exchange_rate_cache;
    private readonly CurrencyCache $currency_cache;
    private readonly AccountCache $account_cache;
    private readonly FrozenAccountCache $frozen_account_cache;
    private readonly CollectionCache $collection_cache;

    private array $loaded_real_unadjusted_summary_calculations = [];
    private array $loaded_real_adjusted_summary_calculations = [];
    private array $loaded_real_flow_calculations = [];

    private bool $has_loaded_for_unfrozen_time_group = false;

    /**
     * Assumes time groups were already sorted by time.
     *
     * @param array $time_groups
     */
    public function __construct(Context $context, array $time_groups)
    {
        $this->context = $context;
        $this->time_groups = $time_groups;
        $this->currency_cache = CurrencyCache::make($this->context);
        $this->account_cache = AccountCache::make($this->context);
        $this->frozen_account_cache = FrozenAccountCache::make($this->context);
        $this->collection_cache = CollectionCache::make($this->context);
        $this->exchange_rate_cache = ExchangeRateCache::make(
            $this->context,
            count($this->time_groups) > 0
                ? $this->time_groups[count($this->time_groups) - 1]->finishedAt()
                : Time::today()->setHour(23)->setMinute(59)->setSecond(59)
        );

        $this->context->setVariable(ContextKeys::TIME_GROUP_MANAGER, $this);

        [
            $earliest_start_date,
            $latest_finish_date,
            $last_frozen_finished_date
        ] = $this->identifyDates();

        $this->context->setVariable(ContextKeys::LATEST_FINISHED_DATE, $latest_finish_date);
        $this->exchange_rate_cache->setLastExchangeRateTimeOnce($latest_finish_date);
    }

    /**
     * Alias for `totalRealOpenedDebitAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalOpenedDebitAmount(array $selected_account_IDs): array
    {
        return $this->totalRealOpenedDebitAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealOpenedCreditAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalOpenedCreditAmount(array $selected_account_IDs): array
    {
        return $this->totalRealOpenedCreditAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealUnadjustedDebitAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalUnadjustedDebitAmount(array $selected_account_IDs): array
    {
        return $this->totalRealUnadjustedDebitAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealUnadjustedCreditAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalUnadjustedCreditAmount(array $selected_account_IDs): array
    {
        return $this->totalRealUnadjustedCreditAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealClosedDebitAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalClosedDebitAmount(array $selected_account_IDs): array
    {
        return $this->totalRealClosedDebitAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealClosedCreditAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalClosedCreditAmount(array $selected_account_IDs): array
    {
        return $this->totalRealClosedCreditAmount($selected_account_IDs);
    }

    /**
     * Alias for `totalRealNetCashFlowAmount` before the complete transition.
     *
     * @deprecated
     */
    public function totalNetCashFlowAmount(
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array {
        return $this->totalRealNetCashFlowAmount($cash_flow_activity_IDs, $selected_account_IDs);
    }

    /**
     * Gets total opened debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealOpenedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadRealAdjustedSummaryCalculations($selected_account_IDs);

        $account_cache = $this->account_cache;

        $debit_hashes = [];
        $credit_hashes = [];
        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hash_group = Resource::group(
            $frozen_account_hashes,
            fn ($frozen_account_hash_info) => $frozen_account_hash_info->account_id
        );

        foreach ($selected_account_IDs as $account_id) {
            $frozen_account_hashes = array_map(
                fn ($frozen_account_hash_info) => $frozen_account_hash_info->hash,
                $frozen_account_hash_group[$account_id]
            );

            if ($account_cache->isDebitedNormally($account_id)) {
                $debit_hashes = array_merge($debit_hashes, $frozen_account_hashes);
            } else {
                $credit_hashes = array_merge($credit_hashes, $frozen_account_hashes);
            }
        }

        return array_map(
            function ($time_group) use ($debit_hashes, $credit_hashes) {
                $debit_amounts = $time_group->totalRealOpenedAmount($debit_hashes);
                $credit_amounts = $time_group->totalRealOpenedAmount($credit_hashes);

                return array_map(
                    fn ($debit_amount, $credit_amount) => $debit_amount->minus($credit_amount),
                    $debit_amounts,
                    $credit_amounts
                );
            },
            $this->time_groups
        );
    }

    /**
     * Gets total opened credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealOpenedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadRealAdjustedSummaryCalculations($selected_account_IDs);

        $account_cache = $this->account_cache;

        $debit_hashes = [];
        $credit_hashes = [];
        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hash_group = Resource::group(
            $frozen_account_hashes,
            fn ($frozen_account_hash_info) => $frozen_account_hash_info->account_id
        );

        foreach ($selected_account_IDs as $account_id) {
            $frozen_account_hashes = array_map(
                fn ($frozen_account_hash_info) => $frozen_account_hash_info->hash,
                $frozen_account_hash_group[$account_id]
            );

            if ($account_cache->isDebitedNormally($account_id)) {
                $debit_hashes = array_merge($debit_hashes, $frozen_account_hashes);
            } else {
                $credit_hashes = array_merge($credit_hashes, $frozen_account_hashes);
            }
        }

        return array_map(
            function ($time_group) use ($debit_hashes, $credit_hashes) {
                $debit_amounts = $time_group->totalRealOpenedAmount($debit_hashes);
                $credit_amounts = $time_group->totalRealOpenedAmount($credit_hashes);

                return array_map(
                    fn ($debit_amount, $credit_amount) => $credit_amount->minus($debit_amount),
                    $debit_amounts,
                    $credit_amounts
                );
            },
            $this->time_groups
        );
    }

    /**
     * Gets total unadjusted debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealUnadjustedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadRealUnadjustedSummaryCalculations($selected_account_IDs);

        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hashes = array_keys($frozen_account_hashes);

        return array_map(
            fn ($time_group) => $time_group->totalRealUnadjustedDebitAmount(
                $frozen_account_hashes
            ),
            $this->time_groups
        );
    }

    /**
     * Gets total unadjusted credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealUnadjustedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadRealUnadjustedSummaryCalculations($selected_account_IDs);

        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hashes = array_keys($frozen_account_hashes);

        return array_map(
            fn ($time_group) => $time_group->totalRealUnadjustedCreditAmount(
                $frozen_account_hashes
            ),
            $this->time_groups
        );
    }

    /**
     * Gets total closed debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealClosedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadRealAdjustedSummaryCalculations($selected_account_IDs);

        $account_cache = $this->account_cache;

        $debit_hashes = [];
        $credit_hashes = [];
        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hash_group = Resource::group(
            $frozen_account_hashes,
            fn ($frozen_account_hash_info) => $frozen_account_hash_info->account_id
        );

        foreach ($selected_account_IDs as $account_id) {
            if (!isset($frozen_account_hash_group[$account_id])) {
                continue;
            }

            $frozen_account_hashes = array_map(
                fn ($frozen_account_hash_info) => $frozen_account_hash_info->hash,
                $frozen_account_hash_group[$account_id]
            );

            if ($account_cache->isDebitedNormally($account_id)) {
                $debit_hashes = array_merge($debit_hashes, $frozen_account_hashes);
            } else {
                $credit_hashes = array_merge($credit_hashes, $frozen_account_hashes);
            }
        }

        return array_map(
            function ($time_group) use ($debit_hashes, $credit_hashes) {
                $debit_amounts = $time_group->totalRealClosedAmount($debit_hashes);
                $credit_amounts = $time_group->totalRealClosedAmount($credit_hashes);

                return array_map(
                    fn ($debit_amount, $credit_amount) => $debit_amount->minus($credit_amount),
                    $debit_amounts,
                    $credit_amounts
                );
            },
            $this->time_groups
        );
    }

    /**
     * Gets total closed credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealClosedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadRealAdjustedSummaryCalculations($selected_account_IDs);

        $account_cache = $this->account_cache;

        $debit_hashes = [];
        $credit_hashes = [];
        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hash_group = Resource::group(
            $frozen_account_hashes,
            fn ($frozen_account_hash_info) => $frozen_account_hash_info->account_id
        );

        foreach ($selected_account_IDs as $account_id) {
            if (!isset($frozen_account_hash_group[$account_id])) {
                continue;
            }

            $frozen_account_hashes = array_map(
                fn ($frozen_account_hash_info) => $frozen_account_hash_info->hash,
                $frozen_account_hash_group[$account_id]
            );

            if ($account_cache->isDebitedNormally($account_id)) {
                $debit_hashes = array_merge($debit_hashes, $frozen_account_hashes);
            } else {
                $credit_hashes = array_merge($credit_hashes, $frozen_account_hashes);
            }
        }

        return array_map(
            function ($time_group) use ($debit_hashes, $credit_hashes) {
                $debit_amounts = $time_group->totalRealClosedAmount($debit_hashes);
                $credit_amounts = $time_group->totalRealClosedAmount($credit_hashes);

                return array_map(
                    fn ($debit_amount, $credit_amount) => $credit_amount->minus($debit_amount),
                    $debit_amounts,
                    $credit_amounts
                );
            },
            $this->time_groups
        );
    }

    /**
     * Gets total net cash flow amount for all selected accounts that participated in specific cash
     * flow activity of every time group.
     *
     * @param int[] $cash_flow_activity_IDs
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalRealNetCashFlowAmount(
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array {
        $this->loadRealFlowCalculations($selected_account_IDs);

        $frozen_account_hashes = $this->frozenAccountHashes($selected_account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return [];
        }

        $frozen_account_hashes = array_keys($frozen_account_hashes);

        return array_map(
            fn ($time_group) => $time_group->totalRealNetCashFlowAmount(
                $cash_flow_activity_IDs,
                $frozen_account_hashes
            ),
            $this->time_groups
        );
    }

    public function timeTags(): array
    {
        return array_map(
            function ($time_group) {
                return $time_group->timeTag();
            },
            $this->time_groups
        );
    }

    public function cycleRanges(): array
    {
        return array_map(
            function ($time_group) {
                $started_at = $time_group->startedAt();
                $finished_at = $time_group->finishedAt();
                return [ $started_at, $finished_at ];
            },
            $this->time_groups
        );
    }

    public function subcycleRanges(): array
    {
        return array_map(
            function ($time_group) {
                $granular_time_ranges = $time_group->granularTimeRanges();
                return $granular_time_ranges;
            },
            $this->time_groups
        );
    }

    private function loadRealUnadjustedSummaryCalculations(array $selected_account_IDs): void
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            array_values($this->loaded_real_unadjusted_summary_calculations)
        );

        if (count($missing_account_IDs) > 0) {
            $this->exchange_rate_cache->loadExchangeRatesForAccounts($missing_account_IDs);

            $frozen_account_hashes = $this->frozenAccountHashes($missing_account_IDs);

            $summary_calculations = count($frozen_account_hashes) > 0
                ? model(RealUnadjustedSummaryCalculationModel::class)
                    ->whereIn("frozen_account_hash", array_keys($frozen_account_hashes))
                    ->findAll()
                : [];

            $exchange_rate_basis = $this->context->getVariable(
                ContextKeys::EXCHANGE_RATE_BASIS,
                PERIODIC_EXCHANGE_RATE_BASIS
            );
            $destination_currency_id = $this->context->getVariable(
                ContextKeys::DESTINATION_CURRENCY_ID
            );

            foreach ($this->time_groups as $time_group) {
                $frozen_period_IDs = $time_group->frozenPeriodIDs();

                if (count($frozen_period_IDs) === 0) {
                    continue;
                }

                $time_basis = $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                    ? Time::today()
                    : $time_group->finishedAt();
                $time_basis = $time_basis->setHour(23)->setMinute(59)->setSecond(59);
                $derivator = $this->exchange_rate_cache->buildDerivator($time_basis);

                foreach ($summary_calculations as $summary_calculation) {
                    $frozen_account_hash = $summary_calculation->frozen_account_hash;
                    $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
                    $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

                    $is_owned = in_array($frozen_period_id, $frozen_period_IDs);
                    if ($is_owned) {
                        $account_id = $frozen_account_hash_info->account_id;
                        $source_currency_id = $this->account_cache->determineCurrencyID(
                            $account_id
                        );
                        $derived_exchange_rate = $derivator->deriveExchangeRate(
                            $source_currency_id,
                            $destination_currency_id ?? $source_currency_id
                        );

                        $summary_calculation->debit_amount
                            = $summary_calculation->debit_amount
                                ->multipliedBy($derived_exchange_rate)
                                ->simplified();
                        $summary_calculation->credit_amount
                            = $summary_calculation->credit_amount
                                ->multipliedBy($derived_exchange_rate)
                                ->simplified();

                        $time_group->addRealUnadjustedSummaryCalculation($summary_calculation);
                        $this->loaded_real_unadjusted_summary_calculations[$frozen_account_hash]
                            = $account_id;
                    }
                }
            }
        }

        $this->loadPossibleLatestForUnfrozenGroup();
    }

    private function loadRealAdjustedSummaryCalculations(array $selected_account_IDs): void
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            array_values($this->loaded_real_adjusted_summary_calculations)
        );

        if (count($missing_account_IDs) > 0) {
            $this->exchange_rate_cache->loadExchangeRatesForAccounts($missing_account_IDs);

            $frozen_account_hashes = $this->frozenAccountHashes($missing_account_IDs);

            $summary_calculations = count($frozen_account_hashes) > 0
                ? model(RealAdjustedSummaryCalculationModel::class)
                    ->whereIn("frozen_account_hash", array_keys($frozen_account_hashes))
                    ->findAll()
                : [];

            $exchange_rate_basis = $this->context->getVariable(
                ContextKeys::EXCHANGE_RATE_BASIS,
                PERIODIC_EXCHANGE_RATE_BASIS
            );
            $destination_currency_id = $this->context->getVariable(
                ContextKeys::DESTINATION_CURRENCY_ID
            );

            foreach ($this->time_groups as $time_group) {
                $frozen_period_IDs = $time_group->frozenPeriodIDs();

                if (count($frozen_period_IDs) === 0) {
                    continue;
                }

                $time_basis = $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                    ? Time::today()
                    : $time_group->finishedAt();
                $time_basis = $time_basis->setHour(23)->setMinute(59)->setSecond(59);
                $derivator = $this->exchange_rate_cache->buildDerivator($time_basis);

                foreach ($summary_calculations as $summary_calculation) {
                    $frozen_account_hash = $summary_calculation->frozen_account_hash;
                    $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
                    $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

                    $is_owned = in_array($frozen_period_id, $frozen_period_IDs);
                    if ($is_owned) {
                        $account_id = $frozen_account_hash_info->account_id;
                        $source_currency_id = $this->account_cache->determineCurrencyID(
                            $account_id
                        );
                        $derived_exchange_rate = $derivator->deriveExchangeRate(
                            $source_currency_id,
                            $destination_currency_id ?? $source_currency_id
                        );

                        $summary_calculation->opened_amount
                            = $summary_calculation->opened_amount
                                ->multipliedBy($derived_exchange_rate)
                                ->simplified();
                        $summary_calculation->closed_amount
                            = $summary_calculation->closed_amount
                                ->multipliedBy($derived_exchange_rate)
                                ->simplified();

                        $time_group->addRealAdjustedSummaryCalculation($summary_calculation);
                        $this->loaded_real_adjusted_summary_calculations[$frozen_account_hash]
                            = $account_id;
                    }
                }
            }
        }

        $this->loadPossibleLatestForUnfrozenGroup();
    }

    private function loadRealFlowCalculations(array $selected_account_IDs): void
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            array_values($this->loaded_real_flow_calculations)
        );

        if (count($missing_account_IDs) > 0) {
            $this->exchange_rate_cache->loadExchangeRatesForAccounts($missing_account_IDs);

            $frozen_account_hashes = $this->frozenAccountHashes($missing_account_IDs);

            $flow_calculations = count($frozen_account_hashes) > 0
                ? model(RealFlowCalculationModel::class)
                    ->whereIn("frozen_account_hash", array_keys($frozen_account_hashes))
                    ->findAll()
                : [];

            $exchange_rate_basis = $this->context->getVariable(
                ContextKeys::EXCHANGE_RATE_BASIS,
                PERIODIC_EXCHANGE_RATE_BASIS
            );
            $destination_currency_id = $this->context->getVariable(
                ContextKeys::DESTINATION_CURRENCY_ID
            );

            foreach ($this->time_groups as $time_group) {
                $frozen_period_IDs = $time_group->frozenPeriodIDs();

                if (count($frozen_period_IDs) === 0) {
                    continue;
                }

                $derivator = $this->exchange_rate_cache->buildDerivator(
                    $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                        ? Time::today()->setHour(23)->setMinute(59)->setSecond(59)
                        : $time_group->finishedAt()
                );

                foreach ($flow_calculations as $flow_calculation) {
                    $frozen_account_hash = $flow_calculation->frozen_account_hash;
                    $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
                    $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

                    $is_owned = in_array($frozen_period_id, $frozen_period_IDs);
                    if ($is_owned) {
                        $account_id = $frozen_account_hash_info->account_id;
                        $source_currency_id = $this->account_cache->determineCurrencyID(
                            $account_id
                        );
                        $derived_exchange_rate = $derivator->deriveExchangeRate(
                            $source_currency_id,
                            $destination_currency_id ?? $source_currency_id
                        );

                        $flow_calculation->net_amount
                            = $flow_calculation->net_amount
                                ->multipliedBy($derived_exchange_rate)
                                ->simplified();

                        $time_group->addRealFlowCalculation($flow_calculation);
                        $this->loaded_real_flow_calculations[] = $account_id;
                    }
                }
            }
        }

        $this->loadPossibleLatestForUnfrozenGroup();
    }

    private function identifyDates(): array
    {
        $earliest_start_date = null;
        $latest_finish_date = null;
        $last_frozen_finished_date = null;

        foreach ($this->time_groups as $time_group) {
            $started_at = $time_group->startedAt();
            $finished_at = $time_group->finishedAt();

            if ($earliest_start_date === null || $started_at->isBefore($earliest_start_date)) {
                $earliest_start_date = $started_at;
            }

            if ($latest_finish_date === null || $finished_at->isAfter($latest_finish_date)) {
                $latest_finish_date = $finished_at;
            }

            $last_frozen_finished_date = $time_group->lastFrozenAt()
                ?? $last_frozen_finished_date;
        }

        // Earliest start date may still be empty if there are no time groups in the first place
        if ($earliest_start_date !== null && $last_frozen_finished_date === null) {
            $last_frozen_finished_date = $earliest_start_date->subDays(1);
        }

        return [
            $earliest_start_date,
            $latest_finish_date,
            $last_frozen_finished_date
        ];
    }

    private function hasUnfrozenTimeGroup(): bool
    {
        $time_group_count = count($this->time_groups);

        if ($time_group_count === 0) {
            return false;
        }

        return $this->time_groups[$time_group_count - 1]->hasSomeUnfrozenDetails();
    }

    private function incompleteFrozenTimeGroup(): ?TimeGroup
    {
        if (!$this->hasUnfrozenTimeGroup()) {
            return null;
        }

        $time_group_count = count($this->time_groups);

        return $this->time_groups[$time_group_count - 1];
    }

    private function loadPossibleLatestForUnfrozenGroup(): void
    {
        $incomplete_frozen_group = $this->incompleteFrozenTimeGroup();
        if ($this->has_loaded_for_unfrozen_time_group || $incomplete_frozen_group === null) {
            return;
        }

        [
            $earliest_start_date,
            $latest_finish_date,
            $last_frozen_finished_date
        ] = $this->identifyDates();

        $current_user = $this->context->user();

        $started_at = $last_frozen_finished_date
            ->addDays(1)
            ->setHour(0)->setMinute(0)->setSecond(0);
        $finished_at = $latest_finish_date->setHour(23)->setMinute(59)->setSecond(59);
        [
            $periodic_frozen_accounts,
            $periodic_real_unadjusted_summaries,
            $periodic_real_adjusted_summaries,
            $periodic_real_flows
        ] = FrozenPeriodModel::makeRawCalculations(
            $current_user,
            $this->context,
            $started_at,
            $finished_at
        );

        $this->frozen_account_cache->addPreloadedResources($periodic_frozen_accounts);
        $account_IDs = array_unique(array_map(function ($frozen_account) {
            return $frozen_account->account_id;
        }, $periodic_frozen_accounts));
        $this->account_cache->loadResources($account_IDs);
        $this->exchange_rate_cache->loadExchangeRatesForAccounts($account_IDs);

        $exchange_rate_basis = $this->context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $destination_currency_id = $this->context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID
        );

        $derivator = $this->exchange_rate_cache->buildDerivator(
            $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? Time::today()->setHour(23)->setMinute(59)->setSecond(59)
                : $latest_finish_date->setHour(23)->setMinute(59)->setSecond(59)
        );

        $frozen_account_hashes = $this->frozenAccountHashes($account_IDs);

        if (count($frozen_account_hashes) === 0) {
            return;
        }

        foreach ($periodic_real_unadjusted_summaries as $summary_calculation) {
            $frozen_account_hash = $summary_calculation->frozen_account_hash;
            $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
            $account_id = $frozen_account_hash_info->account_id;

            $source_currency_id = $this->account_cache->determineCurrencyID($account_id);
            $derived_exchange_rate = $derivator->deriveExchangeRate(
                $source_currency_id,
                $destination_currency_id ?? $source_currency_id
            );

            $summary_calculation->debit_amount
                = $summary_calculation->debit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $summary_calculation->credit_amount
                = $summary_calculation->credit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();

            $incomplete_frozen_group->addRealUnadjustedSummaryCalculation($summary_calculation);
            $this->loaded_real_unadjusted_summary_calculations[$frozen_account_hash]
                = $account_id;
        }

        foreach ($periodic_real_adjusted_summaries as $summary_calculation) {
            $frozen_account_hash = $summary_calculation->frozen_account_hash;
            $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
            $account_id = $frozen_account_hash_info->account_id;

            $source_currency_id = $this->account_cache->determineCurrencyID($account_id);
            $derived_exchange_rate = $derivator->deriveExchangeRate(
                $source_currency_id,
                $destination_currency_id ?? $source_currency_id
            );

            $summary_calculation->opened_amount
                = $summary_calculation->opened_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $summary_calculation->closed_amount
                = $summary_calculation->closed_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();

            $incomplete_frozen_group->addRealAdjustedSummaryCalculation($summary_calculation);
            $this->loaded_real_adjusted_summary_calculations[$frozen_account_hash]
                = $account_id;
        }

        foreach ($periodic_real_flows as $flow_calculation) {
            $frozen_account_hash = $flow_calculation->frozen_account_hash;
            $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
            $account_id = $frozen_account_hash_info->account_id;

            $source_currency_id = $this->account_cache->determineCurrencyID($account_id);
            $derived_exchange_rate = $derivator->deriveExchangeRate(
                $source_currency_id,
                $destination_currency_id ?? $source_currency_id
            );

            $flow_calculation->net_amount
                = $flow_calculation->net_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();

            $incomplete_frozen_group->addRealFlowCalculation($flow_calculation);
            $this->loaded_real_flow_calculations[] = $account_id;
        }

        $this->has_loaded_for_unfrozen_time_group = true;
    }

    private function frozenPeriodIDs(): array
    {
        return array_reduce(
            $this->time_groups,
            fn ($previous_frozen_periods, $current_time_group) => [
                ...$previous_frozen_periods,
                ...$current_time_group->frozenPeriodIDs()
            ],
            []
        );
    }

    private function frozenAccountHashes($selected_account_IDs): array
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            array_values($this->loaded_real_unadjusted_summary_calculations),
            array_values($this->loaded_real_adjusted_summary_calculations),
            array_values($this->loaded_real_flow_calculations)
        );

        if (count($missing_account_IDs) > 0) {
            $frozen_period_IDs = $this->frozenPeriodIDs();

            if (count($frozen_period_IDs) !== 0) {
                $frozen_account_hashes = model(FrozenAccountModel::class, false)
                    ->whereIn("frozen_period_id", $frozen_period_IDs)
                    ->whereIn("account_id", array_unique($missing_account_IDs))
                    ->findAll();

                $this->frozen_account_cache->addPreloadedResources($frozen_account_hashes);
            }
        }

        return $this->frozen_account_cache->selectAccountHashesByAccountID($selected_account_IDs);
    }
}
