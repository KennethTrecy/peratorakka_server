<?php

namespace App\Libraries\TimeGroup;

use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
use App\Contracts\TimeGroup;
use Brick\Math\BigRational;

class PeriodicTimeGroup implements TimeGroup
{
    private readonly FrozenPeriod $frozen_period;

    /**
     * @var SummaryCalculation[]
     */
    private array $summary_calculations = [];

    private array $cache = [];

    public function __construct(FrozenPeriod $frozen_period) {
        $this->frozen_period = $frozen_period;
    }

    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        $does_own_resource = $this->frozen_period->id === $summary_calculation->frozen_period_id;
        if ($does_own_resource) {
            $this->summary_calculations[$summary_calculation->account_id] = $summary_calculation;
        }

        return $does_own_resource;
    }

    public function totalOpenedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->opened_debit_amount);
            },
            BigRational::zero()
        );
    }

    public function totalOpenedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->opened_credit_amount);
            },
            BigRational::zero()
        );
    }

    public function totalUnadjustedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->unadjusted_debit_amount);
            },
            BigRational::zero()
        );
    }

    public function totalUnadjustedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->unadjusted_credit_amount);
            },
            BigRational::zero()
        );
    }

    public function totalClosedDebitAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->closed_debit_amount);
            },
            BigRational::zero()
        );
    }

    public function totalClosedCreditAmount(array $selected_account_IDs): BigRational
    {
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) {
                return $total->plus($summary_calculation->closed_credit_amount);
            },
            BigRational::zero()
        );
    }

    private function selectSummaryCalculations(array $selected_account_IDs): array
    {
        $summary_calculations = $this->summary_calculations;

        $raw_summary_calculations = array_map(
            function ($account_id) use ($summary_calculations) {
                // If summary calculation is not found because does not exist yet during this
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
