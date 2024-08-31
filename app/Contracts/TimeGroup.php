<?php

namespace App\Contracts;

use Brick\Math\BigRational;
use App\Entities\SummaryCalculation;

interface TimeGroup
{
    /**
     * Adds summary calculation only if it belongs to the time group.
     *
     * Returns true if it belongs to the group.
     *
     * @param SummaryCalculation $summary_calculation
     * @return bool
     */
    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalOpenedDebitAmount(array $selected_account_IDs): BigRational;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalOpenedCreditAmount(array $selected_account_IDs): BigRational;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalUnadjustedDebitAmount(array $selected_account_IDs): BigRational;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalUnadjustedCreditAmount(array $selected_account_IDs): BigRational;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalClosedDebitAmount(array $selected_account_IDs): BigRational;

    /**
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalClosedCreditAmount(array $selected_account_IDs): BigRational;
}
