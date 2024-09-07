<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Entities\FlowCalculation;
use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
use CodeIgniter\I18n\Time;

/**
 * Unfrozen time group represents a period that would be considered frozen in the future.
 *
 * All summary calculations without a parent frozen period will be considered unfrozen.
 *
 * There can be only one instance of unfrozen time group.
 */
class UnfrozenTimeGroup extends GranularTimeGroup
{
    private static ?UnfrozenTimeGroup $instance = null;

    public static function make(
        Time $inclusive_start_date,
        Time $inclusive_end_date
    ): UnfrozenTimeGroup {
        if (self::$instance === null) {
            self::$instance = new self($inclusive_start_date, $inclusive_end_date);
        }

        return self::$instance;
    }

    private readonly Time $inclusive_start_date;
    private readonly Time $inclusive_end_date;

    private function __construct(Time $inclusive_start_date, Time $inclusive_end_date)
    {
        $this->inclusive_start_date = $inclusive_start_date;
        $this->inclusive_end_date = $inclusive_end_date;
    }

    public function startedAt(): Time
    {
        return $this->inclusive_start_date;
    }

    public function finishedAt(): Time
    {
        return $this->inclusive_end_date;
    }

    public function hasSomeUnfrozenDetails(): bool
    {
        return true;
    }

    public function addSummaryCalculation(SummaryCalculation $summary_calculation): bool
    {
        $does_own_resource = $summary_calculation->frozen_period_id === 0;
        if ($does_own_resource) {
            $this->summary_calculations[$summary_calculation->account_id] = $summary_calculation;
        }

        return $does_own_resource;
    }

    public function addFlowCalculation(FlowCalculation $flow_calculation): bool
    {
        $does_own_resource = $flow_calculation->frozen_period_id === 0;
        if ($does_own_resource) {
            $this->flow_calculations[$flow_calculation->account_id] = $flow_calculation;
        }

        return $does_own_resource;
    }
}
