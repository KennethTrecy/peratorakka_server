<?php

namespace App\Libraries\NumericalToolConfiguration;

use App\Casts\RationalNumber;
use App\Contracts\NumericalToolSource;
use App\Libraries\Constellation;
use App\Libraries\Constellation\AcceptableConstellationKind;
use App\Libraries\Constellation\Star;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CollectionCache;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\CurrencyCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\MathExpression;
use App\Libraries\TimeGroupManager;
use App\Models\AccountCollectionModel;

class CollectionSource implements NumericalToolSource
{
    public static function sourceType(): string
    {
        return "collection";
    }

    public static function parseConfiguration(array $configuration): ?CollectionSource
    {
        if (
            isset($configuration["collection_id"])
            && isset($configuration["stage_basis"])
            && 0 <= $configuration["stage_basis"]
            && in_array($configuration["stage_basis"], AMOUNT_STAGE_BASES)
            && isset($configuration["side_basis"])
            && 0 <= $configuration["side_basis"]
            && in_array($configuration["side_basis"], AMOUNT_SIDE_BASES)
            && (
                (
                    isset($configuration["must_show_individual_amounts"])
                    && $configuration["must_show_individual_amounts"]
                ) || (
                    isset($configuration["must_show_collective_sum"])
                    && $configuration["must_show_collective_sum"]
                ) || (
                    isset($configuration["must_show_collective_average"])
                    && $configuration["must_show_collective_average"]
                )
            )
        ) {
            return new CollectionSource(
                $configuration["collection_id"],
                $configuration["stage_basis"],
                $configuration["side_basis"],
                $configuration["must_show_individual_amounts"] ?? false,
                $configuration["must_show_collective_sum"] ?? false,
                $configuration["must_show_collective_average"] ?? false
            );
        }

        return null;
    }

    public readonly int $collection_id;
    public readonly string $stage_basis;
    public readonly string $side_basis;
    public readonly bool $must_show_individual_amounts;
    public readonly bool $must_show_collective_sum;
    public readonly bool $must_show_collective_average;

    private function __construct(
        int $collection_id,
        string $stage_basis,
        string $side_basis,
        bool $must_show_individual_amounts,
        bool $must_show_collective_sum,
        bool $must_show_collective_average
    ) {
        $this->collection_id = $collection_id;
        $this->stage_basis = $stage_basis;
        $this->side_basis = $side_basis;
        $this->must_show_individual_amounts = $must_show_individual_amounts;
        $this->must_show_collective_sum = $must_show_collective_sum;
        $this->must_show_collective_average = $must_show_collective_average;
    }

    public function outputFormatCode(): string
    {
        return CURRENCY_FORMULA_OUTPUT_FORMAT;
    }

    public function calculate(Context $context): array
    {
        /**
         * @var TimeGroupManager
         */
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);

        /**
         * @var ExchangeRateCache
         */
        $exchange_rate_cache = $context->getVariable(ContextKeys::EXCHANGE_RATE_CACHE);

        /**
         * @var AccountCache
         */
        $account_cache = $context->getVariable(ContextKeys::ACCOUNT_CACHE);

        /**
         * @var CurrencyCache
         */
        $currency_cache = $context->getVariable(ContextKeys::CURRENCY_CACHE);

        /**
         * @var CollectionCache
         */
        $collection_cache = $context->getVariable(ContextKeys::COLLECTION_CACHE);

        $linked_accounts = $collection_cache->determineAccountIDs($this->collection_id);

        $linked_accounts = array_unique($linked_accounts);
        $linked_account_count = count($linked_accounts);

        if ($linked_account_count === 0) {
            return [];
        }

        /**
         * @var Constellation[]
         */
        $constellations = [];

        $account_totals = [];
        $collective_sum = [];
        $collective_average = [];

        foreach ($linked_accounts as $account_id) {
            $account_debit_totals = [];
            $account_credit_totals = [];
            if (
                $this->side_basis === DEBIT_AMOUNT_SIDE_BASIS
                || $this->side_basis === NET_DEBIT_AMOUNT_SIDE_BASIS
                || $this->side_basis === NET_CREDIT_AMOUNT_SIDE_BASIS
            ) {
                $debit_function = $this->stage_basis === OPENED_AMOUNT_STAGE_BASIS
                    ? "totalRealOpenedDebitAmount"
                    : (
                        $this->stage_basis === UNADJUSTED_AMOUNT_STAGE_BASIS
                            ? "totalRealUnadjustedDebitAmount"
                            : "totalRealClosedDebitAmount"
                    );

                $totals = $time_group_manager->$debit_function([ $account_id ]);
                $account_debit_totals = MathExpression::summatePeriodicResults($totals);
            }

            if (
                $this->side_basis === CREDIT_AMOUNT_SIDE_BASIS
                || $this->side_basis === NET_DEBIT_AMOUNT_SIDE_BASIS
                || $this->side_basis === NET_CREDIT_AMOUNT_SIDE_BASIS
            ) {
                $credit_function = $this->stage_basis === OPENED_AMOUNT_STAGE_BASIS
                    ? "totalRealOpenedCreditAmount"
                    : (
                        $this->stage_basis === UNADJUSTED_AMOUNT_STAGE_BASIS
                            ? "totalRealUnadjustedCreditAmount"
                            : "totalRealClosedCreditAmount"
                    );
                $totals = $time_group_manager->$credit_function([ $account_id ]);
                $account_credit_totals = MathExpression::summatePeriodicResults($totals);
            }

            switch ($this->side_basis) {
                case DEBIT_AMOUNT_SIDE_BASIS:
                    $account_totals[$account_id] = $account_debit_totals;
                    break;
                case CREDIT_AMOUNT_SIDE_BASIS:
                    $account_totals[$account_id] = $account_credit_totals;
                    break;
                case NET_DEBIT_AMOUNT_SIDE_BASIS:
                    $account_totals[$account_id]
                        = array_map(function ($debit_total, $credit_total) {
                            return $debit_total
                                ->minus($credit_total)
                                ->simplified();
                        }, $account_debit_totals, $account_credit_totals);
                    break;
                case NET_CREDIT_AMOUNT_SIDE_BASIS:
                    $account_totals[$account_id]
                        = array_map(function ($debit_total, $credit_total) {
                            return $credit_total
                                ->minus($debit_total)
                                ->simplified();
                        }, $account_debit_totals, $account_credit_totals);
                    break;
            }

            $account_name = $account_cache->determineAccountName($account_id);

            $constellation = new Constellation(
                $account_name,
                AcceptableConstellationKind::Account,
                array_map(
                    function ($time_group_value) use (
                        $account_id,
                        $account_cache,
                        $currency_cache
                    ) {
                        $currency_id = $account_cache->determineCurrencyID($account_id);
                        $display_value = $currency_cache->formatValue(
                            $currency_id,
                            $time_group_value
                        );
                        return new Star($display_value ?? "", $time_group_value);
                    },
                    $account_totals[$account_id]
                )
            );

            if ($this->must_show_individual_amounts) {
                array_push($constellations, $constellation);
            }
        }

        if ($this->must_show_collective_sum) {
            $collective_sum = array_map(
                function ($time_grouped_totals) {
                    return array_reduce($time_grouped_totals, function ($sum, $total) {
                        if ($total === null) {
                            return $sum;
                        }
                        return $sum->plus($total);
                    }, RationalNumber::zero())->simplified();
                },
                count($account_totals) > 1
                    ? array_map(null, ...array_values($account_totals))
                    : array_map(function ($account_total) {
                        return [ $account_total ];
                    }, array_values($account_totals)[0])
            );

            $collection_name = $collection_cache->determineCollectionName($this->collection_id)
                ?? "Collection #$this->collection_id";

            array_push($constellations, new Constellation(
                "Total of $collection_name",
                AcceptableConstellationKind::Sum,
                array_map(
                    function ($sum) use (
                        $account_id,
                        $account_cache,
                        $currency_cache,
                    ) {
                        $currency_id = $account_cache->determineCurrencyID($account_id);
                        $display_value = $currency_cache->formatValue(
                            $currency_id,
                            $sum
                        );
                        return new Star($display_value ?? "", $sum);
                    },
                    $collective_sum
                )
            ));
        }

        if ($this->must_show_collective_average) {
            $collective_average = array_map(
                function ($time_group_collective_sum) use ($linked_account_count) {
                    return $time_group_collective_sum
                        ->dividedBy($linked_account_count)
                        ->simplified();
                },
                $collective_sum
            );

            $collection_name = $collection_cache->determineCollectionName($this->collection_id)
                ?? "Collection #$this->collection_id";

            array_push($constellations, new Constellation(
                "Average of $collection_name",
                AcceptableConstellationKind::Average,
                array_map(
                    function ($average) use (
                        $account_id,
                        $account_cache,
                        $currency_cache
                    ) {
                        $currency_id = $account_cache->determineCurrencyID($account_id);
                        $display_value = $currency_cache->formatValue(
                            $currency_id,
                            $average
                        );
                        return new Star($display_value ?? "", $average);
                    },
                    $collective_average
                )
            ));
        }

        return $constellations;
    }

    public function toArray(): array
    {
        return [
            "type" => static::sourceType(),
            "collection_id" => $this->collection_id,
            "stage_basis" => $this->stage_basis,
            "side_basis" => $this->side_basis,
            "must_show_individual_amounts" => $this->must_show_individual_amounts,
            "must_show_collective_sum" => $this->must_show_collective_sum,
            "must_show_collective_average" => $this->must_show_collective_average
        ];
    }
}
