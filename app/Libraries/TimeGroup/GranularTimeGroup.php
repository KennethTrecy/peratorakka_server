<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use Brick\Math\BigRational;

/**
 * Granular time groups are time groups that contain owned resources directly.
 *
 * Number of allowed instances of granular time group may depend on the child.
 */
abstract class GranularTimeGroup implements TimeGroup
{
    /**
     * @var RealAdjustedSummaryCalculation[]
     */
    protected array $real_adjusted_summary_calculations = [];

    /**
     * @var RealUnadjustedSummaryCalculation[]
     */
    protected array $real_unadjusted_summary_calculations = [];

    /**
     * @var RealFlowCalculation[][]
     */
    protected array $real_flow_calculations = [];

    public function granularTimeRanges(): array
    {
        $started_at = $this->startedAt();
        $finished_at = $this->finishedAt();
        return [ [ $started_at, $finished_at ] ];
    }

    public function timeTag(): string
    {
        $finished_date = $this->finishedAt();

        return $finished_date
            ->setTimezone(DATE_TIME_ZONE)
            ->toLocalizedString("MMMM yyyy");
    }

    public function totalRealOpenedAmount(array $selected_hashes): array
    {
        return [
            array_reduce(
                $this->selectRealAdjustedSummaryCalculations($selected_hashes),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->opened_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealClosedAmount(array $selected_hashes): array
    {
        return [
            array_reduce(
                $this->selectRealAdjustedSummaryCalculations($selected_hashes),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->closed_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealUnadjustedDebitAmount(array $selected_hashes): array
    {
        return [
            array_reduce(
                $this->selectRealUnadjustedSummaryCalculations($selected_hashes),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->debit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealUnadjustedCreditAmount(array $selected_hashes): array
    {
        return [
            array_reduce(
                $this->selectRealUnadjustedSummaryCalculations($selected_hashes),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->credit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealNetCashFlowAmount(
        array $cash_flow_activity_IDs,
        array $selected_hashes
    ): array {
        return [
            array_reduce(
                $this->selectRealFlowCalculations($cash_flow_activity_IDs, $selected_hashes),
                function ($total, $flow_calculation) {
                    return $total->plus($flow_calculation->net_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    private function selectRealAdjustedSummaryCalculations(array $selected_hashes): array
    {
        return $this->selectSummaryCalculations(
            $this->real_adjusted_summary_calculations,
            $selected_hashes
        );
    }

    private function selectRealUnadjustedSummaryCalculations(array $selected_hashes): array
    {
        return $this->selectSummaryCalculations(
            $this->real_unadjusted_summary_calculations,
            $selected_hashes
        );
    }

    private function selectSummaryCalculations(array $source, array $selected_hashes): array
    {
        $raw_summary_calculations = array_map(
            function ($frozen_account_hash) use ($source) {
                // If summary calculation is not found because it does not exist yet during this
                // period, return null.
                return $source[$frozen_account_hash] ?? null;
            },
            $selected_hashes
        );

        $loaded_summary_calculations = array_filter(
            $raw_summary_calculations,
            fn ($summary_calculation) => $summary_calculation !== null
        );

        return $loaded_summary_calculations;
    }

    private function selectRealFlowCalculations(
        array $cash_flow_activity_IDs,
        array $selected_hashes
    ): array {
        $raw_real_flow_calculations = [];

        foreach ($this->real_flow_calculations as $cash_flow_activity_id => $real_flow_calculations) {
            if (in_array($cash_flow_activity_id, $cash_flow_activity_IDs)) {
                $raw_real_flow_calculations = [
                    ...$raw_real_flow_calculations,
                    ...array_map(
                        function ($frozen_account_hash) use ($real_flow_calculations) {
                            // If flow calculation is not found because it does not exist yet during this
                            // period, return null.
                            return $real_flow_calculations[$frozen_account_hash] ?? null;
                        },
                        $selected_hashes
                    )
                ];
            }
        }

        $loaded_flow_calculations = array_filter(
            $raw_real_flow_calculations,
            function ($flow_calculation) {
                return $flow_calculation !== null;
            }
        );

        return $loaded_flow_calculations;
    }
}
