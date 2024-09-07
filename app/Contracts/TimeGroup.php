<?php

namespace App\Contracts;

use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

/**
 * Representation of a data point in a numerical tool.
 *
 * All calculations in a time group are considered to be within the same time period and in the same
 * currencies. Currencies are determined by the numerical tool that owns the instance of time group.
 */
interface TimeGroup
{
    /**
     * Returns the start date of the time group.
     *
     * @return Time
     */
    public function startedAt(): Time;

    /**
     * Returns the finish date of the time group.
     *
     * @return Time
     */
    public function finishedAt(): Time;

    /**
     * Returns true if the time group came from unfrozen period, partially or as a whole.
     *
     * @return boolean
     */
    public function hasSomeUnfrozenDetails(): bool;

    /**
     * Adds summary calculation only if it belongs to the time group.
     *
     * Assumes all calculations added are in the same currency.
     *
     * Returns true if it belongs to the group.
     *
     * @param SummaryCalculation $summary_calculation
     * @return bool
     */
    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool;

    /**
     * Adds flow calculation only if it belongs to the time group.
     *
     * Assumes all calculations added are in the same currency.
     *
     * Returns true if it belongs to the group.
     *
     * @param FlowCalculation $flow_calculation
     * @return bool
     */
    public function addFlowCalculation(FlowCalculation $flow_calculation): bool;

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
