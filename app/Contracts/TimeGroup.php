<?php

namespace App\Contracts;

use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Entities\RealAdjustedSummaryCalculation;
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
     * Returns all ranges of the smallest time groups.
     *
     * @return Time[][]
     */
    public function granularTimeRanges(): array;

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
     * @param RealUnadjustedSummaryCalculation $summary_calculation
     * @return bool
     */
    public function doesOwnRealUnadjustedSummaryCalculation(
        RealUnadjustedSummaryCalculation $summary_calculation
    ): bool;

    /**
     * Returns true if summary calculation belongs to the group.
     *
     * @param RealAdjustedSummaryCalculation $summary_calculation
     * @return bool
     */
    public function doesOwnRealAdjustedSummaryCalculation(
        RealAdjustedSummaryCalculation $summary_calculation
    ): bool;

    /**
     * Returns true if flow calculation belongs to the group.
     *
     * @param RealFlowCalculation $flow_calculation
     * @return bool
     */
    public function doesOwnRealFlowCalculation(RealFlowCalculation $flow_calculation): bool;

    /**
     * Adds summary calculation only if it belongs to the time group.
     *
     * Assumes all calculations added are in the same currency.
     *
     * Returns true if it belongs to the group.
     *
     * @param RealUnadjustedSummaryCalculation $summary_calculation
     * @return bool
     */
    public function addRealUnadjustedSummaryCalculation(
        RealUnadjustedSummaryCalculation $summary_calculation
    ): bool;

    /**
     * Adds summary calculation only if it belongs to the time group.
     *
     * Assumes all calculations added are in the same currency.
     *
     * Returns true if it belongs to the group.
     *
     * @param RealAdjustedSummaryCalculation $summary_calculation
     * @return bool
     */
    public function addRealAdjustedSummaryCalculation(
        RealAdjustedSummaryCalculation $summary_calculation
    ): bool;

    /**
     * Adds flow calculation only if it belongs to the time group.
     *
     * Assumes all calculations added are in the same currency.
     *
     * Returns true if it belongs to the group.
     *
     * @param RealFlowCalculation $flow_calculation
     * @return bool
     */
    public function addRealFlowCalculation(RealFlowCalculation $flow_calculation): bool;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array;

    /**
     * @param Context $context
     * @param int[] $cash_flow_activity_IDs
     * @param int[] $selected_account_IDs
     * @return BigRational[]
     */
    public function totalRealNetCashFlowAmount(
        Context $context,
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array;
}
