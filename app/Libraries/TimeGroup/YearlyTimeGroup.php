<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use CodeIgniter\I18n\Time;

/**
 * Yearly time groups are time groups composed of periodic time groups and/or unfrozen time group.
 *
 * There can be multiple instances of yearly time group.
 */
class YearlyTimeGroup implements TimeGroup
{
    private string $year;
    private bool $is_based_on_start_date;
    private array $time_groups = [];

    public function __construct(string $year, bool $is_based_on_start_date)
    {
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
                    } else if (
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

    public function timeTag(): string {
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

    public function doesOwnSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        foreach ($this->time_groups as $time_group) {
            if ($time_group->doesOwnSummaryCalculation($summary_calculation)) {
                return true;
            }
        }

        return false;
    }

    public function doesOwnFlowCalculation(FlowCalculation $flow_calculation): bool
    {
        foreach ($this->time_groups as $time_group) {
            if ($time_group->doesOwnFlowCalculation($flow_calculation)) {
                return true;
            }
        }

        return false;
    }

    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        foreach ($this->time_groups as $time_group) {
            if ($time_group->addSummaryCalculation($summary_calculation)) {
                return true;
            }
        }

        return false;
    }

    public function addFlowCalculation(FlowCalculation $flow_calculation): bool
    {
        foreach ($this->time_groups as $time_group) {
            if ($time_group->addFlowCalculation($flow_calculation)) {
                return true;
            }
        }

        return false;
    }

    public function totalOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalOpenedDebitAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalOpenedCreditAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalUnadjustedDebitAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalUnadjustedCreditAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalClosedDebitAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $selected_account_IDs) {
                return $time_group->totalClosedCreditAmount($context, $selected_account_IDs)[0];
            },
            $this->time_groups
        );
    }

    public function totalNetCashFlowAmount(
        Context $context,
        int $cash_flow_activity_id,
        array $selected_account_IDs
    ): array {
        return array_map(
            function ($time_group) use ($context, $cash_flow_activity_id, $selected_account_IDs) {
                return $time_group->totalNetCashFlowAmount(
                    $context,
                    $cash_flow_activity_id,
                    $selected_account_IDs
                )[0];
            },
            $this->time_groups
        );
    }
}
