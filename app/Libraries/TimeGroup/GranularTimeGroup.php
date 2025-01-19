<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use Brick\Math\BigRational;

/**
 * Granular time groups are time groups that contain owned resources directly.
 *
 * Number of allowed instances of granular time group may depend on the child.
 */
abstract class GranularTimeGroup implements TimeGroup
{
    /**
     * @var SummaryCalculation[]
     */
    protected array $summary_calculations = [];

    /**
     * @var FlowCalculation[]
     */
    protected array $flow_calculations = [];

    public function granularTimeRanges(): array
    {
        $started_at = $this->startedAt();
        $finished_at = $this->finishedAt();
        return [ [ $started_at, $finished_at ] ];
    }

    public function timeTag(): string {
        $finished_date = $this->finishedAt();

        return $finished_date->toLocalizedString("MMMM yyyy");
    }

    public function totalOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->opened_debit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->opened_credit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->unadjusted_debit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->unadjusted_credit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->closed_debit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->closed_credit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    private function selectSummaryCalculations(array $selected_account_IDs): array
    {
        $summary_calculations = $this->summary_calculations;

        $raw_summary_calculations = array_map(
            function ($account_id) use ($summary_calculations) {
                // If summary calculation is not found because it does not exist yet during this
                // period, return null.
                return $summary_calculations[$account_id] ?? null;
            },
            $selected_account_IDs
        );

        $loaded_summary_calculations = array_filter(
            $raw_summary_calculations,
            function ($summary_calculation) {
                return $summary_calculation !== null;
            }
        );

        return $loaded_summary_calculations;
    }
}
