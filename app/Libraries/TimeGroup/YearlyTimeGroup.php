<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\RealFlowCalculation;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Libraries\Context\FrozenAccountCache;
use App\Libraries\Context;
use CodeIgniter\I18n\Time;

/**
 * Yearly time groups are time groups composed of periodic time groups and/or unfrozen time group.
 *
 * There can be multiple instances of yearly time group.
 */
class YearlyTimeGroup implements TimeGroup
{
    private FrozenAccountCache $frozen_account_cache;
    private string $year;
    private bool $is_based_on_start_date;
    private array $time_groups = [];

    public function __construct(
        FrozenAccountCache $frozen_account_cache,
        string $year,
        bool $is_based_on_start_date
    ) {
        $this->frozen_account_cache = $frozen_account_cache;
        $this->year = $year;
        $this->is_based_on_start_date = $is_based_on_start_date;
    }

    public function addTimeGroup(TimeGroup $time_group): bool
    {
        $base_time = $this->is_based_on_start_date
            ? $time_group->startedAt()
            : $time_group->finishedAt();

        $does_belong = $base_time->year === $this->year;

        if ($does_belong) {
            $time_group_count = count($this->time_groups);
            if ($time_group_count === 0) {
                array_push($this->time_groups, $time_group);
            } else {
                // Insert the new time group as if they would be sorted already from oldest.
                for ($i = 0; $i < $time_group_count; $i++) {
                    $examined_time_group = $this->time_groups[$i];
                    $existing_time = $this->is_based_on_start_date
                        ? $examined_time_group->startedAt()
                        : $examined_time_group->finishedAt();
                    $is_before_examined_time_group = $base_time->isBefore($existing_time);

                    if ($is_before_examined_time_group) {
                        array_splice($this->time_groups, $i, 0, [ $time_group ]);
                        break;
                    } elseif (
                        $i === $time_group_count - 1
                        && $base_time->isAfter($existing_time)
                    ) {
                        array_push($this->time_groups, $time_group);
                    }
                }
            }
        }

        return $does_belong;
    }

    public function frozenPeriodIDs(): array {
        return array_reduce($this->time_groups, fn ($previous_IDs, $current_time_group) => [
            ...$previous_IDs,
            ...$current_time_group->frozenPeriodIDs()
        ], []);
    }

    public function startedAt(): Time
    {
        if (empty($this->time_groups)) {
            return Time::parse($this->year . '-01-01 00:00:00');
        }

        $time_group = $this->time_groups[0];
        return $time_group->startedAt();
    }

    public function finishedAt(): Time
    {
        if (empty($this->time_groups)) {
            return Time::parse($this->year . '-12-31 23:59:59');
        }

        $time_group = $this->time_groups[count($this->time_groups) - 1];
        return $time_group->finishedAt();
    }

    public function granularTimeRanges(): array
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

    public function timeTag(): string
    {
        $finished_date = $this->finishedAt();

        return "$finished_date->year";
    }

    public function hasSomeUnfrozenDetails(): bool
    {
        foreach ($this->time_groups as $time_group) {
            if ($time_group->hasSomeUnfrozenDetails()) {
                return true;
            }
        }

        return false;
    }

    public function addRealUnadjustedSummaryCalculation(
        RealUnadjustedSummaryCalculation $summary_calculation
    ): void {
        $frozen_account_hash = $summary_calculation->frozen_account_hash;
        $frozen_account_hash_info = $this->frozen_account_cache->getLoadedResource(
            $frozen_account_hash
        );
        $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

        foreach ($this->time_groups as $time_group) {
            $frozen_period_IDs = $time_group->frozenPeriodIDs();
            if (in_array($frozen_period_id, $frozen_period_IDs) || (
                !$frozen_period_id && count($frozen_period_IDs) === 0
            )) {
                $time_group->addRealUnadjustedSummaryCalculation($summary_calculation);
                return;
            }
        }
    }

    public function addRealAdjustedSummaryCalculation(
        RealAdjustedSummaryCalculation $summary_calculation
    ): void {
        $frozen_account_hash = $summary_calculation->frozen_account_hash;
        $frozen_account_hash_info = $this->frozen_account_cache->getLoadedResource(
            $frozen_account_hash
        );
        $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

        foreach ($this->time_groups as $time_group) {
            $frozen_period_IDs = $time_group->frozenPeriodIDs();
            if (in_array($frozen_period_id, $frozen_period_IDs) || (
                !$frozen_period_id && count($frozen_period_IDs) === 0
            )) {
                $time_group->addRealAdjustedSummaryCalculation($summary_calculation);
                return;
            }
        }
    }

    public function addRealFlowCalculation(RealFlowCalculation $flow_calculation): void
    {
        $frozen_account_hash = $flow_calculation->frozen_account_hash;
        $frozen_account_hash_info = $this->frozen_account_cache->getLoadedResource(
            $frozen_account_hash
        );
        $frozen_period_id = $frozen_account_hash_info->frozen_period_id;

        foreach ($this->time_groups as $time_group) {
            $frozen_period_IDs = $time_group->frozenPeriodIDs();
            if (in_array($frozen_period_id, $frozen_period_IDs) || (
                !$frozen_period_id && count($frozen_period_IDs) === 0
            )) {
                $time_group->addRealFlowCalculation($flow_calculation);
                return;
            }
        }
    }

    public function totalRealOpenedAmount(
        Context $context,
        array $selected_hashes
    ): array {
        return array_map(
            fn ($time_group) => $time_group->totalRealOpenedAmount($context, $selected_hashes)[0],
            $this->time_groups
        );
    }

    public function totalRealClosedAmount(
        Context $context,
        array $selected_hashes
    ): array {
        return array_map(
            fn ($time_group) => $time_group->totalRealClosedAmount($context, $selected_hashes)[0],
            $this->time_groups
        );
    }

    public function totalRealUnadjustedDebitAmount(
        Context $context,
        array $selected_hashes
    ): array {
        return array_map(
            fn ($time_group) => $time_group->totalRealUnadjustedDebitAmount(
                $context,
                $selected_hashes
            )[0],
            $this->time_groups
        );
    }

    public function totalRealUnadjustedCreditAmount(
        Context $context,
        array $selected_hashes
    ): array {
        return array_map(
            fn ($time_group) => $time_group->totalRealUnadjustedCreditAmount(
                $context,
                $selected_hashes
            )[0],
            $this->time_groups
        );
    }

    public function totalRealNetCashFlowAmount(
        Context $context,
        array $cash_flow_activity_IDs,
        array $selected_hashes
    ): array {
        return array_map(
            fn ($time_group) => $time_group->totalNetCashFlowAmount(
                $context,
                $cash_flow_activity_IDs,
                $selected_account_IDs
            )[0],
            $this->time_groups
        );
    }
}
