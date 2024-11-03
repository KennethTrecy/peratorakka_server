<?php

namespace App\Libraries\TimeGroup;

use App\Entities\FlowCalculation;
use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
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

    public function startedAt(): Time
    {
        return $this->frozen_period->started_at;
    }

    public function finishedAt(): Time
    {
        return $this->frozen_period->finished_at;
    }

    public function hasSomeUnfrozenDetails(): bool
    {
        return false;
    }

    public function doesOwnSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        return $this->frozen_period->id === $summary_calculation->frozen_period_id;
    }

    public function doesOwnFlowCalculation(FlowCalculation $flow_calculation): bool
    {
        return $this->frozen_period->id === $flow_calculation->frozen_period_id;
    }

    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        $does_own_resource = $this->doesOwnSummaryCalculation($summary_calculation);
        if ($does_own_resource) {
            $this->summary_calculations[$summary_calculation->account_id] = $summary_calculation;
        }

        return $does_own_resource;
    }

    public function addFlowCalculation(FlowCalculation $flow_calculation): bool
    {
        $does_own_resource = $this->doesOwnFlowCalculation($flow_calculation);
        if ($does_own_resource) {
            $this->flow_calculations[$flow_calculation->account_id] = $flow_calculation;
        }

        return $does_own_resource;
    }
}
