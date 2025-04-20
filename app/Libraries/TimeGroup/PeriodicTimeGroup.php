<?php

namespace App\Libraries\TimeGroup;

use App\Entities\RealFlowCalculation;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Entities\FrozenPeriod;
use CodeIgniter\I18n\Time;

/**
 * Periodic time groups contain owned resources.
 *
 * There can be multiple instances of periodic time group.
 */
class PeriodicTimeGroup extends GranularTimeGroup
{
    private readonly FrozenPeriod $frozen_period;

    public function __construct(FrozenPeriod $frozen_period)
    {
        $this->frozen_period = $frozen_period;
    }

    public function frozenPeriodIDs(): array
    {
        return [ $this->frozen_period->id ];
    }

    public function startedAt(): Time
    {
        return $this->frozen_period->started_at;
    }

    public function finishedAt(): Time
    {
        return $this->frozen_period->finished_at;
    }

    public function lastFrozenAt(): ?Time
    {
        return $this->finishedAt();
    }

    public function hasSomeUnfrozenDetails(): bool
    {
        return false;
    }

    public function addRealUnadjustedSummaryCalculation(
        RealUnadjustedSummaryCalculation $summary_calculation
    ): void {
        $frozen_account_hash = $summary_calculation->frozen_account_hash;
        $this->real_unadjusted_summary_calculations[$frozen_account_hash] = $summary_calculation;
    }

    public function addRealAdjustedSummaryCalculation(
        RealAdjustedSummaryCalculation $summary_calculation
    ): void {
        $frozen_account_hash = $summary_calculation->frozen_account_hash;
        $this->real_adjusted_summary_calculations[$frozen_account_hash] = $summary_calculation;
    }

    public function addRealFlowCalculation(RealFlowCalculation $flow_calculation): void
    {
        $activity_id = $flow_calculation->cash_flow_activity_id;
        if (!isset($this->real_flow_calculations[$activity_id])) {
            $this->real_flow_calculations[$activity_id] = [];
        }

        $frozen_account_hash = $flow_calculation->frozen_account_hash;
        $this->real_flow_calculations[$activity_id][$frozen_account_hash] = $flow_calculation;
    }
}
