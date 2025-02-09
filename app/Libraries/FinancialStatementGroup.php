<?php

namespace App\Libraries;

use App\Casts\RationalNumber;
use App\Entities\Deprecated\Currency;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;

class FinancialStatementGroup
{
    private readonly array $accounts;
    private readonly array $frozen_accounts;
    private readonly array $real_unadjusted_summaries;
    private readonly array $real_adjusted_summaries;
    private readonly array $real_flows;
    private readonly ExchangeRateDerivator $derivator;

    public function __construct(
        array $accounts,
        array $frozen_accounts,
        array $real_unadjusted_summaries,
        array $real_adjusted_summaries,
        array $real_flows,
        ExchangeRateDerivator $derivator
    ) {
        $this->accounts = Resource::key($accounts, fn ($account) => $account->id);

        $hashed_frozen_accounts = Resource::key(
            $frozen_accounts,
            fn ($frozen_account) => $frozen_account->hash
        );
        $this->frozen_accounts = $hashed_frozen_accounts;
        $this->real_unadjusted_summaries = Resource::key(
            $real_unadjusted_summaries,
            fn ($summary_calculation) => $hashed_frozen_accounts[
                $summary_calculation->frozen_account_hash
            ]->account_id
        );
        $this->real_adjusted_summaries = Resource::key(
            $real_adjusted_summaries,
            fn ($summary_calculation) => $summary_calculation->frozen_account_hash
        );
        $this->real_flows = Resource::group(
            $real_flows,
            fn ($flow_calculation) => $hashed_frozen_accounts[
                $flow_calculation->frozen_account_hash
            ]->account_id
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

        $target_real_unadjusted_summaries = [];
        $target_real_adjusted_summaries = [];
        $target_real_flows = [];

        foreach ($filtered_accounts as $account) {
            if (isset($this->real_unadjusted_summaries[$account->id])) {
                array_push(
                    $target_real_unadjusted_summaries,
                    $this->real_unadjusted_summaries[$account->id]
                );
            }
            if (isset($this->real_adjusted_summaries[$account->id])) {
                array_push(
                    $target_real_adjusted_summaries,
                    $this->real_adjusted_summaries[$account->id]
                );
            }
            if (isset($this->real_flows[$account->id])) {
                array_push(
                    $target_real_flows,
                    ...$this->real_flows[$account->id]
                );
            }
        }

        if (
            count($target_real_unadjusted_summaries) === 0
            && count($target_real_adjusted_summaries) === 0
            && count($target_real_flows) === 0
        ) {
            return null;
        }

        // Compute for income statement and balance sheet
        [
            $unadjusted_total_incomes,
            $unadjusted_total_expenses,
            $unadjusted_total_assets,
            $unadjusted_total_liabilities,
            $unadjusted_total_equities
        ] = $this->totalUnadjustedAccountsGroupedByKind(
            $target_currency,
            $target_real_unadjusted_summaries
        );
        [
            $adjusted_total_assets,
            $adjusted_total_liabilities,
            $adjusted_total_equities
        ] = $this->totalAdjustedAccountsGroupedByKind(
            $target_currency,
            $target_real_adjusted_summaries
        );

        $unadjusted_trial_balance_debit_total = $unadjusted_total_expenses
            ->plus($unadjusted_total_assets);
        $unadjusted_trial_balance_credit_total = $unadjusted_total_equities
            ->plus($unadjusted_total_liabilities)
            ->plus($unadjusted_total_incomes);
        $income_statement_total = $unadjusted_total_incomes
            ->minus($unadjusted_total_expenses);
        $adjusted_trial_balance_debit_total = $adjusted_total_assets;
        $adjusted_trial_balance_credit_total = $adjusted_total_equities
            ->plus($adjusted_total_liabilities);

        // Compute for real cash flow statement
        [
            $opened_real_liquid_amount,
            $closed_real_liquid_amount,
            $real_illiquid_cash_flow_activity_subtotals
        ] = $this->totalFlowCalculations(
            $target_currency,
            $target_real_unadjusted_summaries,
            $target_real_flows
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
                "opened_real_liquid_amount" => $opened_real_liquid_amount->simplified(),
                "closed_real_liquid_amount" => $closed_real_liquid_amount->simplified(),
                "real_liquid_amount_difference" => $closed_real_liquid_amount->minus(
                    $opened_real_liquid_amount
                )->simplified(),
                "subtotals" => $real_illiquid_cash_flow_activity_subtotals
            ],
            "adjusted_trial_balance" => [
                "debit_total" => $adjusted_trial_balance_debit_total->simplified(),
                "credit_total" => $adjusted_trial_balance_credit_total->simplified()
            ]
        ];
    }

    private function totalAdjustedAccountsGroupedByKind(
        Currency $target_currency,
        array $summary_calculations
    ): array {
        $target_currency_id = $target_currency->id;
        $total_assets = RationalNumber::zero();
        $total_liabilities = RationalNumber::zero();
        $total_equities = RationalNumber::zero();

        foreach ($summary_calculations as $summary_calculation) {
            $account_hash = $summary_calculation->frozen_account_hash;
            $account_id = $this->frozen_accounts[$account_hash]->account_id;
            $account = $this->accounts[$account_id];
            $source_currency_id = $account->currency_id;
            $exchange_rate = $this->derivator->deriveExchangeRate(
                $source_currency_id,
                $target_currency_id
            );
            $converted_closed_amount = $summary_calculation
                ->closed_amount
                ->multipliedBy($exchange_rate);

            switch ($account->kind) {
                case GENERAL_ASSET_ACCOUNT_KIND:
                case LIQUID_ASSET_ACCOUNT_KIND:
                case DEPRECIATIVE_ASSET_ACCOUNT_KIND:
                case ITEMIZED_ASSET_ACCOUNT_KIND:
                    $total_assets = $total_assets->plus($converted_closed_amount);
                    break;

                case LIABILITY_ACCOUNT_KIND:
                    $total_liabilities = $total_liabilities->plus($converted_closed_amount);
                    break;

                case EQUITY_ACCOUNT_KIND:
                    $total_equities = $total_equities->plus($converted_closed_amount);
                    break;
            }
        }

        return [
            $total_assets,
            $total_liabilities,
            $total_equities
        ];
    }

    private function totalUnadjustedAccountsGroupedByKind(
        Currency $target_currency,
        array $summary_calculations
    ): array {
        $target_currency_id = $target_currency->id;
        $total_revenues = RationalNumber::zero();
        $total_expenses = RationalNumber::zero();
        $total_assets = RationalNumber::zero();
        $total_liabilities = RationalNumber::zero();
        $total_equities = RationalNumber::zero();

        foreach ($summary_calculations as $summary_calculation) {
            $account_hash = $summary_calculation->frozen_account_hash;
            $account_id = $this->frozen_accounts[$account_hash]->account_id;
            $account = $this->accounts[$account_id];
            $source_currency_id = $account->currency_id;
            $exchange_rate = $this->derivator->deriveExchangeRate(
                $source_currency_id,
                $target_currency_id
            );
            $converted_credit_amount = $summary_calculation
                ->credit_amount
                ->multipliedBy($exchange_rate);
            $converted_debit_amount = $summary_calculation
                ->$debit_amount
                ->multipliedBy($exchange_rate);

            switch ($account->kind) {
                case GENERAL_REVENUE_ACCOUNT_KIND:
                case DIRECT_SALE_ACCOUNT_KIND:
                    $total_revenues = $total_revenues
                        ->plus($converted_credit_amount)
                        ->minus($converted_debit_amount);
                    break;

                case GENERAL_EXPENSE_ACCOUNT_KIND:
                case DIRECT_COST_ACCOUNT_KIND:
                    $total_expenses = $total_expenses
                        ->plus($converted_debit_amount)
                        ->minus($converted_credit_amount);
                    break;

                case GENERAL_TEMPORARY_ACCOUNT_KIND:
                    $total_expenses = $total_expenses->plus($converted_debit_amount);
                    $total_revenues = $total_revenues->plus($converted_credit_amount);
                    break;

                case GENERAL_ASSET_ACCOUNT_KIND:
                case LIQUID_ASSET_ACCOUNT_KIND:
                case DEPRECIATIVE_ASSET_ACCOUNT_KIND:
                case ITEMIZED_ASSET_ACCOUNT_KIND:
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
            $total_revenues,
            $total_expenses,
            $total_assets,
            $total_liabilities,
            $total_equities
        ];
    }

    private function totalFlowCalculations(
        Currency $target_currency,
        array $summary_calculations,
        array $flow_calculations
    ): array {
        $target_currency_id = $target_currency->id;
        $opened_liquid_amount = array_reduce(
            $summary_calculations,
            function ($previous_total, $summary_calculation) use ($target_currency_id) {
                $account_hash = $summary_calculation->frozen_account_hash;
                $account_id = $this->frozen_accounts[$account_hash]->account_id;
                $account = $this->accounts[$account_id];

                if ($account->kind !== LIQUID_ASSET_ACCOUNT_KIND) {
                    return $previous_total;
                }

                $source_currency_id = $account->currency_id;
                $exchange_rate = $this->derivator->deriveExchangeRate(
                    $source_currency_id,
                    $target_currency_id
                );
                $converted_opened_amount = $summary_calculation
                    ->opened_amount
                    ->multipliedBy($exchange_rate);

                return $previous_total->plus($converted_opened_amount);
            },
            RationalNumber::zero()
        );
        $closed_liquid_amount = $opened_liquid_amount;
        $illiquid_cash_flow_activity_subtotals = [];

        foreach ($flow_calculations as $flow_calculation) {
            $activity_id = $flow_calculation->cash_flow_activity_id;

            if (!isset($illiquid_cash_flow_activity_subtotals[$activity_id])) {
                $illiquid_cash_flow_activity_subtotals[$activity_id] = [
                    "cash_flow_activity_id" => $activity_id,
                    "net_income" => RationalNumber::zero(),
                    "subtotal" => RationalNumber::zero()
                ];
            }

            $account_hash = $flow_calculation->frozen_account_hash;
            $account_id = $this->frozen_accounts[$account_hash]->account_id;
            $account = $this->accounts[$account_id];
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

            if (
                $account->kind === GENERAL_EXPENSE_ACCOUNT_KIND
                || $account->kind === GENERAL_REVENUE_ACCOUNT_KIND
                || $account->kind === GENERAL_TEMPORARY_ACCOUNT_KIND
                || $account->kind === DIRECT_COST_ACCOUNT_KIND
                || $account->kind === DIRECT_SALE_ACCOUNT_KIND
            ) {
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
            $opened_liquid_amount,
            $closed_liquid_amount,
            $illiquid_cash_flow_activity_subtotals
        ];
    }
}
