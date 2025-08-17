<?php

namespace App\Libraries\TimeGroup;

use App\Entities\FrozenPeriod;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
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
        Time $inclusive_finish_date
    ): UnfrozenTimeGroup {
        if (self::$instance === null) {
            self::$instance = new self($inclusive_start_date, $inclusive_finish_date);
        }

        return self::$instance;
    }

    private readonly Time $inclusive_start_date;
    private readonly Time $inclusive_finish_date;

    private function __construct(Time $inclusive_start_date, Time $inclusive_finish_date)
    {
        $this->inclusive_start_date = $inclusive_start_date;
        $this->inclusive_finish_date = $inclusive_finish_date;
    }

    public function frozenPeriodIDs(): array
    {
        return [];
    }

    public function startedAt(): Time
    {
        return $this->inclusive_start_date->setTimezone(DATE_TIME_ZONE)
            ->setHour(0)->setMinute(0)->setSecond(0);
    }

    public function finishedAt(): Time
    {
        return $this->inclusive_finish_date->setTimezone(DATE_TIME_ZONE)
            ->setHour(23)->setMinute(59)->setSecond(59);
    }

    public function lastFrozenAt(): ?Time
    {
        return null;
    }

    public function hasSomeUnfrozenDetails(): bool
    {
        return true;
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
