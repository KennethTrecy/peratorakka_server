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
     * Returns the IDs of frozen periods contained by the group.
     *
     * @return int[]
     */
    public function frozenPeriodIDs(): array;

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
     * Adds unadjusted summary calculation keyed its frozen account hash.
     *
     * Assumes all calculations added are in the same currency and belongs to the time group.
     *
     * @param RealUnadjustedSummaryCalculation $summary_calculation
     */
    public function addRealUnadjustedSummaryCalculation(
        RealUnadjustedSummaryCalculation $summary_calculation
    ): void;

    /**
     * Adds adjusted summary calculation keyed its frozen account hash.
     *
     * Assumes all calculations added are in the same currency and belongs to the time group.
     *
     * @param RealAdjustedSummaryCalculation $summary_calculation
     */
    public function addRealAdjustedSummaryCalculation(
        RealAdjustedSummaryCalculation $summary_calculation
    ): void;

    /**
     * Adds flow calculation keyed its frozen account hash.
     *
     * Assumes all calculations added are in the same currency and belongs to the time group.
     *
     * @param RealFlowCalculation $flow_calculation
     */
    public function addRealFlowCalculation(RealFlowCalculation $flow_calculation): void;

    /**
     * @param Context $context
     * @param int[] $selected_hashes
     * @return BigRational[]
     */
    public function totalRealOpenedAmount(Context $context, array $selected_hashes): array;

    /**
     * @param Context $context
     * @param int[] $selected_hashes
     * @return BigRational[]
     */
    public function totalRealClosedAmount(Context $context, array $selected_hashes): array;

    /**
     * @param Context $context
     * @param int[] $selected_hashes
     * @return BigRational[]
     */
    public function totalRealUnadjustedDebitAmount(Context $context, array $selected_hashes): array;

    /**
     * @param Context $context
     * @param int[] $selected_hashes
     * @return BigRational[]
     */
    public function totalRealUnadjustedCreditAmount(
        Context $context,
        array $selected_hashes
    ): array;

    /**
     * @param Context $context
     * @param int[] $cash_flow_activity_IDs
     * @param int[] $selected_hashes
     * @return BigRational[]
     */
    public function totalRealNetCashFlowAmount(
        Context $context,
        array $cash_flow_activity_IDs,
        array $selected_hashes
    ): array;
}
