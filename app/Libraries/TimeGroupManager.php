<?php

namespace App\Libraries\Context;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FrozenPeriod;
use App\Libraries\Context;
use App\Libraries\Resource;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CollectionCache;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\CurrencyCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Models\RealFlowCalculationModel;
use App\Models\RealUnadjustedSummaryCalculationModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use App\Models\FrozenAccountModel;
use App\Models\FrozenPeriodModel;
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
    private readonly CollectionCache $collection_cache;

    private array $loaded_frozen_account_hashes = [];
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
        $this->currency_cache = new CurrencyCache($this->context);
        $this->account_cache = new AccountCache($this->context);
        $this->collection_cache = new CollectionCache($this->context);
        $this->exchange_rate_cache = new ExchangeRateCache(
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
    }

    /**
     * Gets total opened debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalOpenedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalOpenedDebitAmount($context, $selected_account_IDs);
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
    public function totalOpenedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalOpenedCreditAmount($context, $selected_account_IDs);
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
    public function totalUnadjustedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalUnadjustedDebitAmount($context, $selected_account_IDs);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total unadjusted credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalUnadjustedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalUnadjustedCreditAmount($context, $selected_account_IDs);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total closed debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_IDs
     * @return BigRational[][]
     */
    public function totalClosedDebitAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalClosedDebitAmount($context, $selected_account_IDs);
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
    public function totalClosedCreditAmount(array $selected_account_IDs): array
    {
        $this->loadSummaryCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalClosedCreditAmount($context, $selected_account_IDs);
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
    public function totalNetCashFlowAmount(
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array {
        $this->loadFlowCalculations($selected_account_IDs);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $cash_flow_activity_IDs, $selected_account_IDs) {
                return $time_group->totalNetCashFlowAmount(
                    $context,
                    $cash_flow_activity_IDs,
                    $selected_account_IDs
                );
            },
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

            $frozen_account_hashes = $this->frozenAccountHashes();
            $summary_calculations = model(RealUnadjustedSummaryCalculationModel::class)
                ->whereIn("frozen_account_hash", array_keys($frozen_account_hashes))
                ->findAll();

            $exchange_rate_basis = $this->context->getVariable(
                ContextKeys::EXCHANGE_RATE_BASIS,
                PERIODIC_EXCHANGE_RATE_BASIS
            );
            $destination_currency_id = $this->context->getVariable(
                ContextKeys::DESTINATION_CURRENCY_ID
            );

            // TODO: Move this condition to caller of this method
            // if (!is_null($destination_currency_id)) {
            //     $this->exchange_rate_cache->loadExchangeRatesForCurrencies([
            //         $destination_currency_id
            //     ]);
            // }

            foreach ($this->time_groups as $time_group) {
                $derivator = $this->exchange_rate_cache->buildDerivator(
                    $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                        ? Time::today()->setHour(23)->setMinute(59)->setSecond(59)
                        : $time_group->finishedAt()
                );

                foreach ($summary_calculations as $summary_calculation) {
                    $frozen_account_hash = $summary_calculation->frozen_account_hash;
                    $frozen_account_hash_info = $frozen_account_hashes[$frozen_account_hash];
                    $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

                    $is_owned = $time_group->doesRepresentFrozenPeriod($frozen_period_id);
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

        // $this->loadPossibleLatestForUnfrozenGroup();
    }

    private function loadFlowCalculations(array $selected_account_IDs): void
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            $this->loaded_flow_calculations_by_account_id
        );

        if (count($missing_account_IDs) > 0) {
            $this->exchange_rate_cache->loadExchangeRatesForAccounts($missing_account_IDs);

            $flow_calculations = model(FlowCalculationModel::class)
                ->whereIn("account_id", array_unique($missing_account_IDs))
                ->findAll();

            $exchange_rate_basis = $this->context->getVariable(
                ContextKeys::EXCHANGE_RATE_BASIS,
                PERIODIC_EXCHANGE_RATE_BASIS
            );
            $destination_currency_id = $this->context->getVariable(
                ContextKeys::DESTINATION_CURRENCY_ID,
                null
            );
            if (!is_null($destination_currency_id)) {
                $this->exchange_rate_cache->loadExchangeRatesForCurrencies([
                    $destination_currency_id
                ]);
            }
            foreach ($this->time_groups as $time_group) {
                $derivator = $this->exchange_rate_cache->buildDerivator(
                    $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                        ? Time::today()->setHour(23)->setMinute(59)->setSecond(59)
                        : $time_group->finishedAt()
                );

                foreach ($flow_calculations as $flow_calculation) {
                    $is_owned = $time_group->doesOwnFlowCalculation($flow_calculation);
                    if ($is_owned) {
                        $account_id = $flow_calculation->account_id;
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

                        $time_group->addFlowCalculation($flow_calculation);
                        $this->loaded_flow_calculations_by_account_id[] = $account_id;
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

            if (!$time_group->hasSomeUnfrozenDetails()) {
                $last_frozen_finished_date = $latest_finish_date;
            }
        }

        if ($last_frozen_finished_date === null) {
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

        $current_user = auth()->user();

        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
        ] = FrozenPeriodModel::makeRawCalculations(
            $current_user,
            $last_frozen_finished_date->addDays(1)->setHour(0)->setMinute(0)->setSecond(0),
            $latest_finish_date->setHour(23)->setMinute(59)->setSecond(59)
        );

        $account_IDs = array_unique(array_map(function ($account) {
            return $account->id;
        }, array_values($accounts)));
        $this->account_cache->loadAccounts($account_IDs);
        $this->exchange_rate_cache->loadExchangeRatesForAccounts($account_IDs);

        $destination_currency_id = $this->context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        if (!is_null($destination_currency_id)) {
            $this->exchange_rate_cache->loadExchangeRatesForCurrencies([
                $destination_currency_id
            ]);
        }

        $exchange_rate_basis = $this->context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $this->exchange_rate_cache->buildDerivator(
            $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? Time::today()->setHour(23)->setMinute(59)->setSecond(59)
                : $latest_finish_date->setHour(23)->setMinute(59)->setSecond(59)
        );

        foreach ($raw_summary_calculations as $raw_summary_calculation) {
            $account_id = $raw_summary_calculation->account_id;
            $source_currency_id = $this->account_cache->determineCurrencyID($account_id);
            $derived_exchange_rate = $derivator->deriveExchangeRate(
                $source_currency_id,
                $destination_currency_id ?? $source_currency_id
            );
            $raw_summary_calculation->opened_debit_amount
                = $raw_summary_calculation->opened_debit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $raw_summary_calculation->opened_credit_amount
                = $raw_summary_calculation->opened_credit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $raw_summary_calculation->unadjusted_debit_amount
                = $raw_summary_calculation->unadjusted_debit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $raw_summary_calculation->unadjusted_credit_amount
                = $raw_summary_calculation->unadjusted_credit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $raw_summary_calculation->closed_debit_amount
                = $raw_summary_calculation->closed_debit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $raw_summary_calculation->closed_credit_amount
                = $raw_summary_calculation->closed_credit_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $incomplete_frozen_group->addSummaryCalculation($raw_summary_calculation);
        }

        foreach ($raw_flow_calculations as $raw_flow_calculation) {
            $account_id = $raw_flow_calculation->account_id;
            $source_currency_id = $this->account_cache->determineCurrencyID($account_id);
            $derived_exchange_rate = $derivator->deriveExchangeRate(
                $source_currency_id,
                $destination_currency_id ?? $source_currency_id
            );
            $raw_flow_calculation->net_amount
                = $raw_flow_calculation->net_amount
                    ->multipliedBy($derived_exchange_rate)
                    ->simplified();
            $incomplete_frozen_group->addFlowCalculation($raw_flow_calculation);
        }

        $this->has_loaded_for_unfrozen_time_group = true;
    }

    private function frozenPeriodIDs(): array {
        return array_reduce(
            $this->time_groups,
            fn ($previous_frozen_periods, $current_time_group) => [
                ...$previous_frozen_periods,
                ...$current_time_group->frozenPeriodIDs()
            ],
            []
        );
    }

    private function frozenAccountHashes($selected_account_IDs): array {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            array_map(
                fn ($account_hash_info) => $account_hash_info->account_id,
                $this->loaded_frozen_account_hashes
            )
        );

        if (count($missing_account_IDs) > 0) {
            $frozen_account_hashes = model(FrozenAccountModel::class)
                ->whereIn("frozen_period_id", $this->frozenPeriodIDs())
                ->whereIn("account_id", array_unique($missing_account_IDs));

            $frozen_account_hashes = Resource::key(
                $frozen_account_hashes,
                fn ($frozen_account_hash) => $frozen_account_hash->hash
            );

            $this->loaded_frozen_account_hashes = array_merge(
                $this->loaded_frozen_account_hashes,
                $frozen_account_hash
            );
        }

        return array_filter(
            $this->loaded_frozen_account_hashes,
            fn ($frozen_account_hash_info) => in_array(
                $frozen_account_hash_info->account_id,
                $selected_account_IDs
            )
        );
    }
}
