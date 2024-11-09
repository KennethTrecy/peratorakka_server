<?php

namespace App\Libraries\Context\TimeGroupManager;

use App\Casts\ModifierAction;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context;
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

    public function buildDerivator(Time $target_time): ExchangeRateDerivator
    {
        if (isset($built_derivators[$target_time])) {
            return $built_derivators[$target_time];
        }

        $qualified_exchange_rates = array_filter(
            $this->exchange_entries,
            function ($exchange_entry) use ($target_time) {
                return $exchange_entry->updated_at->isBefore($target_time)
                    || $exchange_entry->updated_at->equals($target_time);
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

        $new_derivator = new ExchangeRateDerivator(array_values($updated_exchange_rates));
        $built_derivators[$target_time] = $new_derivator;

        return $new_derivator;
    }

    public function loadExchangeRatesForAccounts(array $missing_account_IDs): void {
        $currency_cache = $this->context->getVariable(ContextKeys::CURRENCY_CACHE);
        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);

        $account_cache->loadAccounts($missing_account_IDs);

        $target_currency_IDs = array_unique(array_map(function ($account_id) use ($account_cache) {
            return $account_cache->determineCurrencyID($account_id);
        }, $missing_account_IDs));

        $new_currency_IDs = array_diff($target_currency_IDs, $this->known_currency_IDs);
        $all_known_IDs = array_unique(array_merge($this->known_currency_IDs, $new_currency_IDs));

        if (count($new_currency_IDs) > 0 && $this->last_exchange_rate_time->getTimestamp() > 0) {
            $currency_cache->loadCurrencies($new_currency_IDs);

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
                $debit_account_id = $modifier->debit_account_id;
                $debit_account_kind = $account_cache->determineAccountKind($debit_account_id);
                $credit_account_id = $modifier->credit_account_id;

                $may_use_debit_account_as_destination
                    = $debit_account_kind === GENERAL_ASSET_ACCOUNT_KIND
                        || $debit_account_kind === LIQUID_ASSET_ACCOUNT_KIND
                        || $debit_account_kind === DEPRECIATIVE_ASSET_ACCOUNT_KIND
                        || $debit_account_kind === EXPENSE_ACCOUNT_KIND;
                $debit_currency_id = $account_cache->determineCurrencyID($debit_account_id);
                $credit_currency_id = $account_cache->determineCurrencyID($credit_account_id);
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
