<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use Brick\Math\BigRational;
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
    ): BigRational {
        return array_reduce(
            array_slice($this->time_groups, 0, 1),
            function ($total, $time_group) use ($context, $selected_account_IDs) {
                return $total->plus(
                    $time_group->totalOpenedDebitAmount($context, $selected_account_IDs)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        return array_reduce(
            array_slice($this->time_groups, 0, 1),
            function ($total, $time_group) use ($context, $selected_account_IDs) {
                return $total->plus(
                    $time_group->totalOpenedCreditAmount($context, $selected_account_IDs)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $temporary_account_IDs = array_filter(
            $selected_account_IDs,
            function ($account_id) use ($account_cache) {
                $kind = $account_cache->determineAccountKind($account_id);
                return $kind === INCOME_ACCOUNT_KIND || $kind === EXPENSE_ACCOUNT_KIND;
            }
        );
        $permanent_account_IDs = array_diff($selected_account_IDs, $temporary_account_IDs);

        $sum_of_temporary_accounts = array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($context, $temporary_account_IDs) {
                return $total->plus(
                    $time_group->totalUnadjustedDebitAmount($context, $temporary_account_IDs)
                );
            },
            RationalNumber::zero()
        );
        $sum_of_permanent_accounts = array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($context, $permanent_account_IDs) {
                return $total->plus(
                    $time_group->totalUnadjustedDebitAmount($context, $permanent_account_IDs)
                    ->minus(
                        $time_group->totalOpenedDebitAmount($context, $permanent_account_IDs)
                    )
                );
            },
            count($this->time_groups) === 0
                ? RationalNumber::zero()
                : $this->time_groups[0]->totalOpenedDebitAmount($context, $permanent_account_IDs)
        );

        return $sum_of_temporary_accounts->plus($sum_of_permanent_accounts);
    }

    public function totalUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $temporary_account_IDs = array_filter(
            $selected_account_IDs,
            function ($account_id) use ($account_cache) {
                $kind = $account_cache->determineAccountKind($account_id);
                return $kind === INCOME_ACCOUNT_KIND || $kind === EXPENSE_ACCOUNT_KIND;
            }
        );
        $permanent_account_IDs = array_diff($selected_account_IDs, $temporary_account_IDs);

        $sum_of_temporary_accounts = array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($context, $temporary_account_IDs) {
                return $total->plus(
                    $time_group->totalUnadjustedCreditAmount($context, $temporary_account_IDs)
                );
            },
            RationalNumber::zero()
        );
        $sum_of_permanent_accounts = array_reduce(
            $this->time_groups,
            function ($total, $time_group) use ($context, $permanent_account_IDs) {
                return $total->plus(
                    $time_group->totalUnadjustedCreditAmount($context, $permanent_account_IDs)
                    ->minus(
                        $time_group->totalOpenedCreditAmount($context, $permanent_account_IDs)
                    )
                );
            },
            count($this->time_groups) === 0
                ? RationalNumber::zero()
                : $this->time_groups[0]->totalOpenedCreditAmount($context, $permanent_account_IDs)
        );

        return $sum_of_temporary_accounts->plus($sum_of_permanent_accounts);
    }

    public function totalClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        return array_reduce(
            array_slice($this->time_groups, count($this->time_groups) - 1, 1),
            function ($total, $time_group) use ($context, $selected_account_IDs) {
                return $total->plus(
                    $time_group->totalClosedDebitAmount($context, $selected_account_IDs)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        return array_reduce(
            array_slice($this->time_groups, count($this->time_groups) - 1, 1),
            function ($total, $time_group) use ($context, $selected_account_IDs) {
                return $total->plus(
                    $time_group->totalClosedCreditAmount($context, $selected_account_IDs)
                );
                ;
            },
            RationalNumber::zero()
        );
    }
}
