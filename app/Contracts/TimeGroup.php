<?php

namespace App\Contracts;

use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Libraries\Context;
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
     * Returns a human-readable time tag useful for displaying the name of time group.
     *
     * @return string
     */
    public function timeTag(): string;

    /**
     * Returns true if the time group came from unfrozen period, partially or as a whole.
     *
     * @return bool
     */
    public function hasSomeUnfrozenDetails(): bool;

    /**
     * Returns true if summary calculation belongs to the group.
     *
     * @param SummaryCalculation $summary_calculation
     * @return bool
     */
    public function doesOwnSummaryCalculation(SummaryCalculation $summary_calculation): bool;

    /**
     * Returns true if flow calculation belongs to the group.
     *
     * @param FlowCalculation $flow_calculation
     * @return bool
     */
    public function doesOwnFlowCalculation(FlowCalculation $flow_calculation): bool;

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
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational
     */
    public function totalClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational;
}
