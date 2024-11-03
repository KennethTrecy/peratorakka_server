<?php

namespace App\Libraries\TimeGroupManager;

use App\Casts\ModifierAction;
use App\Libraries\MathExpression\ContextKeys;
use App\Libraries\MathExpression\Context;
use App\Libraries\FinancialStatementGroup\ExchangeRateInfo;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;
use App\Models\FinancialEntryModel;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

class ExchangeRateCache {
    public readonly Context $context;
    private array $accounts = [];
    private array $exchange_entries = [];
    private array $known_currency_IDs = [];
    private array $built_derivators = [];
    private Time $last_exchange_rate_time;

    public function __construct(Context $context, Time $last_exchange_rate_time)
    {
        $this->context = $context;
        $this->last_exchange_rate_time = $last_exchange_rate_time;

        $this->context->setVariable(ContextKeys::EXCHANGE_RATE_CACHE, $this);
    }

    public function buildDerivator(Time $targetTime): ExchangeRateDerivator
    {
        $qualified_exchange_rates = array_filter(
            $this->exchange_entries,
            function ($exchange_entry) use ($targetTime) {
                return $exchange_entry->updated_at->isBefore($targetTime)
                    || $exchange_entry->updated_at->equals($targetTime);
            }
        );
        $updated_exchange_rates = [];
        foreach ($qualified_exchange_rates as $exchange_entry) {
            $exchange_id = $exchange_entry->source_currency_id
                ."_"
                .$exchange_entry->destination_currency_id;

            if (
                !isset($updated_exchange_rates[$exchange_id])
                || $exchange_entry->updated_at->isBefore(
                    $updated_exchange_rates[$exchange_id]->updated_at
                )
            ) {
                $updated_exchange_rates[$exchange_id] = $exchange_entry;
            }
        }

        return new ExchangeRateDerivator(array_values($updated_exchange_rates));
    }

    public function determineCurrencyIDUsingAccountID(int $account_id): ?int {
        return isset($this->accounts[$account_id])
            ? $this->accounts[$account_id]->currency_id
            : null;
    }

    public function loadAccounts(array $missing_account_IDs): void {
        $new_accounts = model(AccountModel::class, false)
            ->whereIn("id", array_unique($missing_account_IDs))
            ->findAll();

        $this->accounts = array_replace(
            $this->accounts,
            Resource::key($new_accounts, function ($account) {
                return $account->id;
            })
        );

        $target_currency_IDs = array_unique(array_map(function ($account) {
            return $account->currency_id;
        }, $new_accounts));

        $new_currency_IDs = array_diff($target_currency_IDs, $this->known_currency_IDs);
        $all_known_IDs = array_unique(array_merge($this->known_currency_IDs, $new_currency_IDs));

        if (count($new_currency_IDs) > 0 && $this->last_exchange_rate_time->getTimestamp() > 0) {
            $this->known_currency_IDs = $all_known_IDs;

            $all_account_subquery = model(AccountModel::class, false)
                ->builder()
                ->select("id")
                ->whereIn("currency_id", $all_known_IDs);
            $new_account_subquery = model(AccountModel::class, false)
                ->builder()
                ->select("id")
                ->whereIn("currency_id", $new_currency_IDs);

            $new_exchange_modifiers = Resource::key(
                model(ModifierModel::class, false)
                    ->groupStart()
                        ->groupStart()
                            ->whereIn("debit_account_id", $new_account_subquery)
                            ->whereIn("credit_account_id", $all_account_subquery)
                        ->groupEnd()
                        ->orGroupStart()
                            ->whereIn("debit_account_id", $all_account_subquery)
                            ->whereIn("credit_account_id", $new_account_subquery)
                        ->groupEnd()
                    ->groupEnd()
                    ->where("action", ModifierAction::set(EXCHANGE_MODIFIER_ACTION))
                    ->whereIn(
                        "id",
                        model(FinancialEntryModel::class, false)
                            ->builder()
                            ->select("modifier_id")
                            ->where(
                                "transacted_at <=",
                                $this->last_exchange_rate_time
                            )
                    )
                    ->findAll(),
                function ($modifier) {
                    return $modifier->id;
                }
            );

            $new_exchange_entries = model(FinancialEntryModel::class, false)
                ->where(
                    "transacted_at <=",
                    $this->last_exchange_rate_time
                )
                ->whereIn(
                    "modifier_id",
                    array_keys($new_exchange_modifiers)
                )
                ->findAll();

            foreach ($new_exchange_entries as $financial_entry) {
                $modifier = $new_exchange_modifiers[$financial_entry->modifier_id];
                $debit_account = $this->accounts[$modifier->debit_account_id];
                $credit_account = $this->accounts[$modifier->credit_account_id];

                $may_use_debit_account_as_destination
                    = $debit_account->kind === GENERAL_ASSET_ACCOUNT_KIND
                        || $debit_account->kind === LIQUID_ASSET_ACCOUNT_KIND
                        || $debit_account->kind === DEPRECIATIVE_ASSET_ACCOUNT_KIND
                        || $debit_account->kind === EXPENSE_ACCOUNT_KIND;
                $debit_currency_id = $debit_account->currency_id;
                $credit_currency_id = $credit_account->currency_id;
                $debit_value = $financial_entry->debit_amount;
                $credit_value = $financial_entry->credit_amount;

                $source_currency_id = $may_use_debit_account_as_destination
                    ? $credit_currency_id
                    : $debit_currency_id;
                $destination_currency_id = $may_use_debit_account_as_destination
                    ? $debit_currency_id
                    : $credit_currency_id;

                $source_value = $may_use_debit_account_as_destination
                    ? $credit_value
                    : $debit_value;
                $destination_value = $may_use_debit_account_as_destination
                    ? $debit_value
                    : $credit_value;

                $new_exchange_rate_info = new ExchangeRateInfo(
                    $source_currency_id,
                    $source_value,
                    $destination_currency_id,
                    $destination_value,
                    $financial_entry->transacted_at
                );

                array_push($this->exchange_entries, $new_exchange_rate_info);
            }
        }
    }
}
