<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\RealFlowCalculation;
use App\Entities\RealAdjustedSummaryCalculation;
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
    protected array $flow_calculations = [];

    public function granularTimeRanges(): array
    {
        $started_at = $this->startedAt();
        $finished_at = $this->finishedAt();
        return [ [ $started_at, $finished_at ] ];
    }

    public function timeTag(): string
    {
        $finished_date = $this->finishedAt();

        return $finished_date->toLocalizedString("MMMM yyyy");
    }

    public function totalRealOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        [
            $debit_summary_calculations,
            $credit_summary_calculations
        ] = $this->selectRealAdjustedSummaryCalculations($context, $selected_account_IDs);

        return [
            array_reduce(
                $credit_summary_calculations,
                function ($total, $summary_calculation) {
                    return $total->minus($summary_calculation->opened_amount);
                },
                array_reduce(
                    $debit_summary_calculations,
                    function ($total, $summary_calculation) {
                        return $total->reduce($summary_calculation->opened_amount);
                    },
                    RationalNumber::zero()
                )
            )
        ];
    }

    public function totalRealOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        [
            $debit_summary_calculations,
            $credit_summary_calculations
        ] = $this->selectRealAdjustedSummaryCalculations($context, $selected_account_IDs);

        return [
            array_reduce(
                $debit_summary_calculations,
                function ($total, $summary_calculation) {
                    return $total->minus($summary_calculation->opened_amount);
                },
                array_reduce(
                    $credit_summary_calculations,
                    function ($total, $summary_calculation) {
                        return $total->reduce($summary_calculation->opened_amount);
                    },
                    RationalNumber::zero()
                )
            )
        ];
    }

    public function totalRealUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectRealUnadjustedSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->debit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectRealUnadjustedSummaryCalculations($selected_account_IDs),
                function ($total, $summary_calculation) {
                    return $total->plus($summary_calculation->credit_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    public function totalRealClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        [
            $debit_summary_calculations,
            $credit_summary_calculations
        ] = $this->selectRealAdjustedSummaryCalculations($context, $selected_account_IDs);

        return [
            array_reduce(
                $credit_summary_calculations,
                function ($total, $summary_calculation) {
                    return $total->minus($summary_calculation->closed_amount);
                },
                array_reduce(
                    $debit_summary_calculations,
                    function ($total, $summary_calculation) {
                        return $total->reduce($summary_calculation->closed_amount);
                    },
                    RationalNumber::zero()
                )
            )
        ];
    }

    public function totalRealClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): array {
        [
            $debit_summary_calculations,
            $credit_summary_calculations
        ] = $this->selectRealAdjustedSummaryCalculations($context, $selected_account_IDs);

        return [
            array_reduce(
                $debit_summary_calculations,
                function ($total, $summary_calculation) {
                    return $total->minus($summary_calculation->closed_amount);
                },
                array_reduce(
                    $credit_summary_calculations,
                    function ($total, $summary_calculation) {
                        return $total->reduce($summary_calculation->closed_amount);
                    },
                    RationalNumber::zero()
                )
            )
        ];
    }

    public function totalRealNetCashFlowAmount(
        Context $context,
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array {
        return [
            array_reduce(
                $this->selectRealFlowCalculations($cash_flow_activity_IDs, $selected_account_IDs),
                function ($total, $flow_calculation) {
                    return $total->plus($flow_calculation->net_amount);
                },
                RationalNumber::zero()
            )
        ];
    }

    private function selectRealAdjustedSummaryCalculations(
        Context $context,
        array $selected_account_IDs
    ): array {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE, null);
        $summary_calculations = $this->real_adjusted_summary_calculations;

        $raw_debit_summary_calculations = [];
        $raw_credit_summary_calculations = [];
        foreach ($selected_account_IDs as $account_id) {
            if (isset($summary_calculations[$account_id])) {
                $account_kind = $account_cache->determineAccountKind($account_id);
                if (in_array($account_kind, NORMAL_DEBIT_ACCOUNT_KINDS)) {
                    array_push(
                        $raw_debit_summary_calculations,
                        $summary_calculations[$account_id]
                    );
                } else {
                    array_push(
                        $raw_credit_summary_calculations,
                        $summary_calculations[$account_id]
                    );
                }
            }
        }

        return [ $raw_debit_summary_calculations, $raw_credit_summary_calculations ];
    }

    private function selectRealUnajustedSummaryCalculations(array $selected_account_IDs): array
    {
        $real_unadjusted_summary_calculations = $this->real_unadjusted_summary_calculations;

        $raw_summary_calculations = array_map(
            function ($account_id) use ($real_unadjusted_summary_calculations) {
                // If summary calculation is not found because it does not exist yet during this
                // period, return null.
                return $real_unadjusted_summary_calculations[$account_id] ?? null;
            },
            $selected_account_IDs
        );

        $loaded_summary_calculations = array_filter(
            $raw_summary_calculations,
            function ($summary_calculation) {
                return $summary_calculation !== null;
            }
        );

        return $loaded_summary_calculations;
    }

    private function selectRealFlowCalculations(
        array $cash_flow_activity_IDs,
        array $selected_account_IDs
    ): array {
        $raw_real_flow_calculations = [];

        foreach ($this->flow_calculations as $cash_flow_activity_id => $flow_calculations) {
            if (in_array($cash_flow_activity_id, $cash_flow_activity_IDs)) {
                $raw_real_flow_calculations = [
                    ...$raw_real_flow_calculations,
                    ...array_map(
                        function ($account_id) use ($flow_calculations) {
                            // If flow calculation is not found because it does not exist yet during this
                            // period, return null.
                            return $flow_calculations[$account_id] ?? null;
                        },
                        $selected_account_IDs
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
