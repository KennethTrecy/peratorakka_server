<?php

namespace App\Libraries\Context;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Libraries\Context\TimeGroupManager\CurrencyCache;
use App\Libraries\Context\TimeGroupManager\AccountCache;
use App\Libraries\Context\TimeGroupManager\CollectionCache;
use App\Libraries\Context\TimeGroupManager\ExchangeRateCache;
use App\Models\FrozenPeriodModel;
use App\Models\SummaryCalculationModel;

class TimeGroupManager
{
    public readonly Context $context;

    private readonly array $time_groups;
    private readonly ExchangeRateCache $exchange_rate_cache;
    private readonly CurrencyCache $currency_cache;
    private readonly AccountCache $account_cache;
    private readonly CollectionCache $collection_cache;

    private array $loaded_summary_calculations_by_account_id = [];

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
            $this->time_groups[count($this->time_groups) - 1]->finishedAt()
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
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalOpenedDebitAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalOpenedDebitAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total opened credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalOpenedCreditAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalOpenedCreditAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total unadjusted debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalUnadjustedDebitAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalUnadjustedDebitAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total unadjusted credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalUnadjustedCreditAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalUnadjustedCreditAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total closed debit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalClosedDebitAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalClosedDebitAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    /**
     * Gets total closed credit amount for all selected accounts of every time group.
     *
     * @param int[] $selected_account_ids
     * @return BigRational[]
     */
    public function totalClosedCreditAmount(array $selected_account_ids): array
    {
        $this->loadSummaryCalculations($selected_account_ids);

        $context = $this->context;

        return array_map(
            function ($time_group) use ($context, $selected_account_ids) {
                return $time_group->totalClosedCreditAmount($context, $selected_account_ids);
            },
            $this->time_groups
        );
    }

    private function loadSummaryCalculations(array $selected_account_IDs): void
    {
        $missing_account_IDs = array_diff(
            $selected_account_IDs,
            $this->loaded_summary_calculations_by_account_id
        );

        if (count($missing_account_IDs) > 0) {
            $this->exchange_rate_cache->loadExchangeRatesForAccounts($missing_account_IDs);

            $summary_calculations = model(SummaryCalculationModel::class)
                ->whereIn("account_id", array_unique($missing_account_IDs))
                ->findAll();

            foreach ($this->time_groups as $time_group) {
                $derivator = $this->exchange_rate_cache->buildDerivator($time_group->finishedAt());
                $destination_currency_id = $this->context->getVariable(
                    ContextKeys::DESTINATION_CURRENCY_ID,
                    null
                );

                foreach ($summary_calculations as $summary_calculation) {
                    $is_owned = $time_group->doesOwnSummaryCalculation($summary_calculation);
                    if ($is_owned) {
                        $account_id = $summary_calculation->account_id;

                        $time_group->addSummaryCalculation($summary_calculation);
                        $this->loaded_summary_calculations_by_account_id[] = $account_id;
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
            $last_frozen_finished_date = $latest_finish_date;
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

        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
        ] = FrozenPeriodModel::makeRawCalculations(
            $last_frozen_finished_date->setHour(0)->setMinute(0)->setSecond(0),
            $latest_finish_date->setHour(23)->setMinute(59)->setSecond(59)
        );

        foreach ($raw_summary_calculations as $raw_summary_calculation) {
            $incomplete_frozen_group->addSummaryCalculation($raw_summary_calculation);
        }

        foreach ($raw_flow_calculations as $raw_flow_calculation) {
            $incomplete_frozen_group->addFlowCalculation($raw_flow_calculation);
        }

        $this->has_loaded_for_unfrozen_time_group = true;
    }
}
