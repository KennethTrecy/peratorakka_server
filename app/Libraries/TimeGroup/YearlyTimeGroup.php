<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

/**
 * Yearly time groups are time groups composed periodic time groups and/or unfrozen time group.
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
                $this->time_groups[] = $time_group;
            } else {
                $i = 0;
                for (; $i < $time_group_count; ++$i) {
                    $examined_time_group = $this->time_groups[$i];
                    $existing_time = $this->is_based_on_start_date
                        ? $examined_time_group->startedAt()
                        : $examined_time_group->finishedAt();
                    if ($base_time->isBefore($existing_time)) {
                        array_splice($this->time_groups, $i, 0, $time_group);
                        break;
                    }
                }

                if ($i === $time_group_count) {
                    $this->time_groups[] = $time_group;
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

    public function totalOpenedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus($time_group->totalOpenedDebitAmount($selected_account_IDs));
            },
            RationalNumber::zero()
        );
    }

    public function totalOpenedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus($time_group->totalOpenedCreditAmount($selected_account_IDs));
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus($time_group->totalUnadjustedDebitAmount($selected_account_IDs));
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus(
                    $time_group->totalUnadjustedCreditAmount($selected_account_IDs)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus($time_group->totalClosedDebitAmount($selected_account_IDs));
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($selected_account_IDs) {
                return $total->plus($time_group->totalClosedCreditAmount($selected_account_IDs));
                ;
            },
            RationalNumber::zero()
        );
    }
}
