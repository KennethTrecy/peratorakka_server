<?php

namespace App\Libraries;

use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
use App\Models\SummaryCalculationModel;
use App\Contracts\TimeGroup;
use Brick\Math\BigRational;

class TimeGroupManager
{
    private readonly array $time_groups;

    private array $loaded_summary_calculations_by_account_id = [];

    public function __construct(array $time_groups) {
        $this->time_groups = $time_groups;
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalOpenedDebitAmount($selected_account_ids);
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalOpenedCreditAmount($selected_account_ids);
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalUnadjustedDebitAmount($selected_account_ids);
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalUnadjustedCreditAmount($selected_account_ids);
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalClosedDebitAmount($selected_account_ids);
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
        return array_map(
            function ($time_group) use ($selected_account_ids) {
                return $time_group->totalClosedCreditAmount($selected_account_ids);
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
            $summary_calculations = model(SummaryCalculationModel::class)
                ->whereIn("account_id", array_unique($missing_account_IDs))
                ->findAll();

            foreach ($summary_calculations as $summary_calculation) {
                foreach ($this->time_groups as $time_group) {
                    $is_added = $time_group->addSummaryCalculation($summary_calculation);
                    if ($is_added) {
                        $account_id = $summary_calculation->account_id;
                        $this->loaded_summary_calculations_by_account_id[] = $account_id;
                        continue 2;
                    }
                }
            }
        }
    }
}
