<?php

namespace App\Libraries\Context;

use App\Casts\ModifierAction;
use App\Casts\ModifierAtomKind;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\FinancialStatementGroup\ExchangeRateInfo;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\FinancialEntryAtomModel;
use App\Models\FinancialEntryModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierModel;
use CodeIgniter\I18n\Time;

class ExchangeRateCache extends SingletonCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::EXCHANGE_RATE_CACHE;
    }

    private array $exchange_entries = [];
    private array $known_currency_IDs = [];
    private array $built_derivators = [];
    private ?Time $last_exchange_rate_time = null;

    public function setLastExchangeRateTimeOnce(Time $last_exchange_rate_time)
    {
        if ($this->last_exchange_rate_time === null) {
            $this->last_exchange_rate_time = $last_exchange_rate_time;
        }
    }

    public function buildDerivator(Time $target_time): ExchangeRateDerivator
    {
        $target_timestamp = $target_time->getTimestamp();
        if (isset($built_derivators[$target_timestamp])) {
            return $built_derivators[$target_timestamp];
        }

        $qualified_exchange_rates = array_filter(
            $this->exchange_entries,
            function ($exchange_entry) use ($target_time) {
                return $exchange_entry->updated_at->isBefore($target_time)
                    || $exchange_entry->updated_at->equals($target_time);
            }
        );

        $updated_exchange_rates = [];
        foreach ($qualified_exchange_rates as $qualified_exchange_entry) {
            $exchange_id = $qualified_exchange_entry->source_currency_id
                ."_"
                .$qualified_exchange_entry->destination_currency_id;

            if (
                !isset($updated_exchange_rates[$exchange_id])
                || $qualified_exchange_entry->updated_at->isAfter(
                    $updated_exchange_rates[$exchange_id]->updated_at
                )
            ) {
                // Choose the newer exchange entry.
                $updated_exchange_rates[$exchange_id] = $qualified_exchange_entry;
            }
        }

        $new_derivator = new ExchangeRateDerivator(array_values($updated_exchange_rates));
        $built_derivators[$target_timestamp] = $new_derivator;

        return $new_derivator;
    }

    public function loadExchangeRatesForAccounts(array $target_account_IDs): void
    {
        $account_cache = AccountCache::make($this->context);

        $accounts = [];
        $missing_account_IDs = [];
        foreach ($target_account_IDs as $target_account_id) {
            $account = $account_cache->getLoadedResource($target_account_id);

            if (is_null($account)) {
                array_push($missing_account_IDs, $target_account_id);
            } else {
                array_push($accounts, $account);
            }
        }

        foreach ($missing_account_IDs as $missing_account_id) {
            $account = $account_cache->getLoadedResource($missing_account_id);
            array_push($accounts, $account);
        }

        $target_currency_IDs = array_unique(array_map(
            fn ($account) => $account->currency_id,
            $accounts
        ));

        $this->loadExchangeRatesForCurrencies($target_currency_IDs);
    }

    public function loadExchangeRatesForCurrencies(array $new_currency_IDs): void
    {
        $currency_cache = CurrencyCache::make($this->context);
        $account_cache = AccountCache::make($this->context);
        $modifier_atom_cache = ModifierAtomCache::make($this->context);

        $unknown_IDs = array_diff($new_currency_IDs, $this->known_currency_IDs);
        $all_known_IDs = array_unique(array_merge($this->known_currency_IDs, $unknown_IDs));

        if (count($unknown_IDs) > 0 && !is_null($this->last_exchange_rate_time)) {
            $currency_cache->loadResources($unknown_IDs);

            $exchange_modifier_subquery = model(ModifierModel::class, false)
                ->builder()
                ->select("id")
                ->where("action", ModifierAction::set(EXCHANGE_MODIFIER_ACTION));
            $all_account_subquery = model(AccountModel::class, false)
                ->builder()
                ->select("id")
                ->whereIn("currency_id", $all_known_IDs);
            $new_account_subquery = model(AccountModel::class, false)
                ->builder()
                ->select("id")
                ->whereIn("currency_id", $new_currency_IDs);

            $financial_entry_subquery =  model(FinancialEntryModel::class, false)
                ->builder()
                ->select("id")
                ->where(
                    "transacted_at <=",
                    $this->last_exchange_rate_time
                )
                ->whereIn(
                    "id",
                    model(FinancialEntryAtomModel::class, false)
                        ->builder()
                        ->select("financial_entry_id")
                        ->whereIn(
                            "modifier_atom_id",
                            model(ModifierAtomModel::class, false)
                                ->builder()
                                ->select("id")
                                ->where("modifier_id", $exchange_modifier_subquery)
                                ->where("account_id", $new_account_subquery)
                        )
                        ->whereIn(
                            "modifier_atom_id",
                            model(ModifierAtomModel::class, false)
                                ->builder()
                                ->select("id")
                                ->where("modifier_id", $exchange_modifier_subquery)
                                ->where("account_id", $all_account_subquery)
                        )
                );

            $financial_entries = model(FinancialEntryModel::class, false)
                ->whereIn("id", $financial_entry_subquery)
                ->findAll();
            $financial_entries = Resource::key(
                $financial_entries,
                fn ($financial_entry) => $financial_entry->id
            );

            $financial_entry_atoms = model(FinancialEntryAtomModel::class, false)
                ->whereIn("financial_entry_id", $financial_entry_subquery)
                ->findAll();

            $linked_modifier_atoms = array_map(
                fn ($atom) => $atom->modifier_atom_id,
                $financial_entry_atoms
            );
            $modifier_atom_cache->loadResources(array_unique($linked_modifier_atoms));

            $associated_accounts = $modifier_atom_cache->extractAssociatedAccountIDs();
            $linked_accounts = array_unique(array_values($associated_accounts));
            $account_cache->loadResources($linked_accounts);

            $paired_financial_entry_atoms = Resource::group(
                $financial_entry_atoms,
                fn ($atom) => $atom->financial_entry_id
            );

            foreach ($paired_financial_entry_atoms as $entry_id => $financial_entry_atom_pair) {
                [
                    $debit_financial_entry_atom,
                    $credit_financial_entry_atom
                ] = $financial_entry_atom_pair;

                $debit_modifier_atom_id = $debit_financial_entry_atom->modifier_atom_id;
                $credit_modifier_atom_id = $credit_financial_entry_atom->modifier_atom_id;
                $debit_account_id = $modifier_atom_cache
                    ->determineModifierAtomAccountID($debit_modifier_atom_id);
                $credit_account_id = $modifier_atom_cache
                    ->determineModifierAtomAccountID($credit_modifier_atom_id);

                $debit_account_kind = $account_cache->determineAccountKind($debit_account_id);

                $may_use_debit_account_as_destination = in_array(
                    $debit_account_kind,
                    NORMAL_DEBIT_ACCOUNT_KINDS
                );
                $debit_currency_id = $account_cache->determineCurrencyID($debit_account_id);
                $credit_currency_id = $account_cache->determineCurrencyID($credit_account_id);
                $debit_value = $debit_financial_entry_atom->numerical_value;
                $credit_value = $credit_financial_entry_atom->numerical_value;

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
                    $financial_entries[$entry_id]->transacted_at->addSeconds($entry_id)
                );

                array_push($this->exchange_entries, $new_exchange_rate_info);
            }

            $this->known_currency_IDs = $all_known_IDs;
        }
    }
}
