<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
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

    public function totalOpenedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->opened_debit_amount);
            },
            RationalNumber::zero()
        );
    }

    public function totalOpenedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->opened_credit_amount);
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->unadjusted_debit_amount);
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->unadjusted_credit_amount);
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->closed_debit_amount);
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->closed_credit_amount);
            },
            RationalNumber::zero()
        );
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
