<?php

namespace App\Libraries\TimeGroup;

use App\Casts\RationalNumber;
use App\Contracts\TimeGroup;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
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
     * @var SummaryCalculation[]
     */
    protected array $summary_calculations = [];

    /**
     * @var FlowCalculation[]
     */
    protected array $flow_calculations = [];

    public function timeTag(): string {
        $finishedDate = $this->finishedAt();

        return $finishedDate->toLocalizedString("MMMM");
    }

    public function totalOpenedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );

        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->opened_debit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalOpenedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->opened_credit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->unadjusted_debit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalUnadjustedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->unadjusted_credit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedDebitAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->closed_debit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    public function totalClosedCreditAmount(
        Context $context,
        array $selected_account_IDs
    ): BigRational {
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);
        $exchange_rate_basis = $context->getVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            PERIODIC_EXCHANGE_RATE_BASIS
        );
        $derivator = $exchange_rate_cache->buildDerivator(
             $exchange_rate_basis === LATEST_EXCHANGE_RATE_BASIS
                ? $this->finishedAt()
                : $context->getVariable(ContextKeys::LATEST_FINISHED_DATE)
        );
        $destination_currency_id = $context->getVariable(
            ContextKeys::DESTINATION_CURRENCY_ID,
            null
        );
        return array_reduce(
            $this->selectSummaryCalculations($selected_account_IDs),
            function ($total, $summary_calculation) use (
                $account_cache,
                $derivator,
                $destination_currency_id
            ) {
                $account_id = $summary_calculation->account_id;
                $source_currency_id = $account_cache->determineCurrencyID($account_id);
                $derived_exchange_rate = is_null($source_currency_id)
                    ? RationalNumber::get("0/1")
                    : (
                        is_null($destination_currency_id)
                            ? RationalNumber::get("1")
                            : $derivator->deriveExchangeRate(
                                $source_currency_id,
                                $destination_currency_id
                            )
                    );

                return $total->plus(
                    $summary_calculation
                        ->closed_credit_amount
                        ->multipliedBy($derived_exchange_rate)
                );
            },
            RationalNumber::zero()
        );
    }

    private function selectSummaryCalculations(array $selected_account_IDs): array
    {
        $summary_calculations = $this->summary_calculations;

        $raw_summary_calculations = array_map(
            function ($account_id) use ($summary_calculations) {
                // If summary calculation is not found because it does not exist yet during this
                // period, return null.
                return $summary_calculations[$account_id] ?? null;
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
}
