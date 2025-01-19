<?php

namespace App\Libraries;

use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Entities\Currency;
use App\Casts\RationalNumber;

class FinancialStatementGroup
{
    private readonly array $accounts;
    private readonly array $summary_calculations;
    private readonly array $flow_calculations;
    private readonly ExchangeRateDerivator $derivator;

    public function __construct(
        array $accounts,
        array $summary_calculations,
        array $flow_calculations,
        ExchangeRateDerivator $derivator
    ) {
        $this->accounts = Resource::key($accounts, function ($account) {
            return $account->id;
        });
        $this->summary_calculations = Resource::key(
            $summary_calculations,
            function ($summary_calculation) {
                return $summary_calculation->account_id;
            }
        );
        $this->flow_calculations = Resource::group(
            $flow_calculations,
            function ($flow_calculation) {
                return $flow_calculation->account_id;
            }
        );
        $this->derivator = $derivator;
    }

    public function generateFinancialStatements(
        ?Currency $source_currency,
        Currency $target_currency
    ): ?array {
        $filtered_accounts = array_filter(
            $this->accounts,
            function ($account) use ($source_currency) {
                return $source_currency === null || $account->currency_id === $source_currency->id;
            }
        );

        if (count($filtered_accounts) === 0) {
            return null;
        }

        $target_summary_calculations = [];
        $target_flow_calculations = [];

        foreach ($filtered_accounts as $account) {
            if (isset($this->summary_calculations[$account->id])) {
                array_push($target_summary_calculations, $this->summary_calculations[$account->id]);
            }

            if (isset($this->flow_calculations[$account->id])) {
                array_push($target_flow_calculations, ...$this->flow_calculations[$account->id]);
            }
        }

        if (count($target_summary_calculations) === 0 && count($target_flow_calculations) === 0) {
            return null;
        }

        // Compute for income statement and balance sheet
        [
            $unadjusted_total_incomes,
            $unadjusted_total_expenses,
            $unadjusted_total_assets,
            $unadjusted_total_liabilities,
            $unadjusted_total_equities
        ] = $this->totalAccountsGroupByKind(
            "unadjusted",
            $target_currency,
            $target_summary_calculations
        );
        [
            $adjusted_total_incomes,
            $adjusted_total_expenses,
            $adjusted_total_assets,
            $adjusted_total_liabilities,
            $adjusted_total_equities
        ] = $this->totalAccountsGroupByKind(
            "closed",
            $target_currency,
            $target_summary_calculations
        );

        $unadjusted_trial_balance_debit_total = $unadjusted_total_expenses
            ->plus($unadjusted_total_assets);
        $unadjusted_trial_balance_credit_total = $unadjusted_total_equities
            ->plus($unadjusted_total_liabilities)
            ->plus($unadjusted_total_incomes);
        $income_statement_total = $unadjusted_total_incomes
            ->minus($unadjusted_total_expenses);
        $adjusted_trial_balance_debit_total = $adjusted_total_expenses
            ->plus($adjusted_total_assets);
        $adjusted_trial_balance_credit_total = $adjusted_total_equities
            ->plus($adjusted_total_liabilities)
            ->plus($adjusted_total_incomes);

        // Compute for cash flow statement
        $target_currency_id = $target_currency->id;
        $opened_liquid_amount = array_reduce(
            $target_summary_calculations,
            function ($previous_total, $summary_calculation) use ($target_currency_id) {
                $account = $this->accounts[$summary_calculation->account_id];

                if ($account->kind !== LIQUID_ASSET_ACCOUNT_KIND) {
                    return $previous_total;
                }

                $source_currency_id = $account->currency_id;
                $exchange_rate = $this->derivator->deriveExchangeRate(
                    $source_currency_id,
                    $target_currency_id
                );
                $converted_debit_amount = $summary_calculation
                    ->opened_debit_amount
                    ->multipliedBy($exchange_rate);

                return $previous_total->plus($converted_debit_amount);
            },
            RationalNumber::zero()
        );
        $closed_liquid_amount = $opened_liquid_amount;
        $illiquid_cash_flow_activity_subtotals = [];

        foreach ($target_flow_calculations as $flow_calculation) {
            $activity_id = $flow_calculation->cash_flow_activity_id;

            if (!isset($illiquid_cash_flow_activity_subtotals[$activity_id])) {
                $illiquid_cash_flow_activity_subtotals[$activity_id] = [
                    "cash_flow_activity_id" => $activity_id,
                    "net_income" => RationalNumber::zero(),
                    "subtotal" => RationalNumber::zero()
                ];
            }

            $account = $this->accounts[$flow_calculation->account_id];
            $source_currency_id = $account->currency_id;
            $exchange_rate = $this->derivator->deriveExchangeRate(
                $source_currency_id,
                $target_currency_id
            );
            $net_amount = $flow_calculation->net_amount->multipliedBy($exchange_rate);

            $closed_liquid_amount = $closed_liquid_amount->plus($net_amount);

            $illiquid_cash_flow_activity_subtotals[$activity_id]["subtotal"]
                = $illiquid_cash_flow_activity_subtotals[$activity_id]["subtotal"]
                    ->plus($net_amount);

            if ($account->kind === EXPENSE_ACCOUNT_KIND || $account->kind === INCOME_ACCOUNT_KIND) {
                $illiquid_cash_flow_activity_subtotals[$activity_id]["net_income"]
                    = $illiquid_cash_flow_activity_subtotals[$activity_id]["net_income"]
                        ->plus($net_amount);
            }
        }

        $illiquid_cash_flow_activity_subtotals = array_map(
            function ($subtotal_info) {
                return array_merge($subtotal_info, [
                    "net_income" => $subtotal_info["net_income"]->simplified(),
                    "subtotal" => $subtotal_info["subtotal"]->simplified()
                ]);
            },
            array_filter(
                array_values($illiquid_cash_flow_activity_subtotals),
                function ($cash_flow_activity_subtotal) {
                    return $cash_flow_activity_subtotal["subtotal"]->getSign() !== 0;
                }
            )
        );

        return [
            "currency_id" => $source_currency === null ? null : $source_currency->id,
            "unadjusted_trial_balance" => [
                "debit_total" => $unadjusted_trial_balance_debit_total->simplified(),
                "credit_total" => $unadjusted_trial_balance_credit_total->simplified()
            ],
            "income_statement" => [
                "net_total" => $income_statement_total->simplified()
            ],
            "balance_sheet" => [
                "total_assets" => $unadjusted_total_assets->simplified(),
                "total_liabilities" => $unadjusted_total_liabilities->simplified(),
                "total_equities" => $unadjusted_total_equities
                    ->plus($income_statement_total)
                    ->simplified()
            ],
            "cash_flow_statement" => [
                "opened_liquid_amount" => $opened_liquid_amount->simplified(),
                "closed_liquid_amount" => $closed_liquid_amount->simplified(),
                "liquid_amount_difference" => $closed_liquid_amount->minus(
                    $opened_liquid_amount
                )->simplified(),
                "subtotals" => $illiquid_cash_flow_activity_subtotals
            ],
            "adjusted_trial_balance" => [
                "debit_total" => $adjusted_trial_balance_debit_total->simplified(),
                "credit_total" => $adjusted_trial_balance_credit_total->simplified()
            ]
        ];
    }

    private function totalAccountsGroupByKind(
        string $stage,
        Currency $target_currency,
        array $summary_calculations
    ): array {
        $target_currency_id = $target_currency->id;
        $total_incomes = RationalNumber::zero();
        $total_expenses = RationalNumber::zero();
        $total_assets = RationalNumber::zero();
        $total_liabilities = RationalNumber::zero();
        $total_equities = RationalNumber::zero();

        foreach ($summary_calculations as $summary_calculation) {
            $debit_key = "{$stage}_debit_amount";
            $credit_key = "{$stage}_credit_amount";
            $account = $this->accounts[$summary_calculation->account_id];
            $source_currency_id = $account->currency_id;
            $exchange_rate = $this->derivator->deriveExchangeRate(
                $source_currency_id,
                $target_currency_id
            );
            $converted_credit_amount = $summary_calculation
                ->$credit_key
                ->multipliedBy($exchange_rate);
            $converted_debit_amount = $summary_calculation
                ->$debit_key
                ->multipliedBy($exchange_rate);

            switch ($account->kind) {
                case INCOME_ACCOUNT_KIND:
                    $total_incomes = $total_incomes
                        ->plus($converted_credit_amount)
                        ->minus($converted_debit_amount);
                    break;

                case EXPENSE_ACCOUNT_KIND:
                    $total_expenses = $total_expenses
                        ->plus($converted_debit_amount)
                        ->minus($converted_credit_amount);
                    break;

                case GENERAL_ASSET_ACCOUNT_KIND:
                case LIQUID_ASSET_ACCOUNT_KIND:
                case DEPRECIATIVE_ASSET_ACCOUNT_KIND:
                    $total_assets = $total_assets
                        ->plus($converted_debit_amount)
                        ->minus($converted_credit_amount);
                    break;

                case LIABILITY_ACCOUNT_KIND:
                    $total_liabilities = $total_liabilities
                        ->plus($converted_credit_amount)
                        ->minus($converted_debit_amount);
                    break;

                case EQUITY_ACCOUNT_KIND:
                    $total_equities = $total_equities
                        ->plus($converted_credit_amount)
                        ->minus($converted_debit_amount);
                    break;
            }
        }

        return [
            $total_incomes,
            $total_expenses,
            $total_assets,
            $total_liabilities,
            $total_equities
        ];
    }
}
