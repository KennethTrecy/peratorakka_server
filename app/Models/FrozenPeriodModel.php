<?php

namespace App\Models;

use App\Casts\RationalNumber;
use App\Entities\Deprecated\FlowCalculation;
use App\Entities\FrozenPeriod;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CashFlowActivityCache;
use App\Libraries\Context\CurrencyCache;
use App\Libraries\Context\ModifierAtomActivityCache;
use App\Libraries\Context\ModifierAtomCache;
use App\Libraries\Context\ModifierCache;
use App\Libraries\Resource;
use App\Models\FrozenAccountModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use App\Models\RealUnadjustedSummaryCalculationModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class FrozenPeriodModel extends BaseResourceModel
{
    protected $table = "frozen_periods";
    protected $returnType = FrozenPeriod::class;
    protected $allowedFields = [
        "user_id",
        "started_at",
        "finished_at"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [
        "started_at",
        "finished_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "started_at"  => Time::yesterday()->toDateTimeString(),
            "finished_at"  => Time::now()->toDateTimeString()
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }

    public static function findLatestPeriod(string $latest_time): ?FrozenPeriod
    {
        return model(FrozenPeriodModel::class, false)
            ->where("finished_at <", $latest_time)
            ->orderBy("finished_at", "DESC")
            ->first();
    }

    public static function makeRawCalculations(
        User $user,
        Context $context,
        string $started_at,
        string $finished_at
    ): array {
        $financial_entry_model = model(FinancialEntryModel::class, false);
        $financial_entries = $financial_entry_model
            ->limitSearchToUser($financial_entry_model, $user)
            ->where("transacted_at >=", $started_at)
            ->where("transacted_at <=", $finished_at)
            ->withDeleted()
            ->findAll();

        // Happens for new users
        if (count($financial_entries) === 0) {
            return [
                [], // cash flow activities
                [], // accounts
                [], // real unadjusted summary calculations
                [], // real adjusted summary calculations
                [], // real flow calculations
                [] // raw exchange rates
            ];
        }

        [
            $associated_accounts,
            $associated_cash_flow_activities,
            $financial_entry_atoms
        ] = static::loadLinkedResourcesAndFinancialEntryAtoms(
            $context,
            $financial_entries
        );

        $associated_account_hashes = static::generateAccountHashes(
            $started_at,
            $finished_at,
            $associated_accounts
        );

        [
            // Used to determine previous period
            $earliest_transacted_time,
            // Use to determine the exchange rate to use
            $latest_entry_transacted_time
        ] = static::minMaxTransactedTimes($financial_entries);

        [
            $previous_keyed_real_raw_adjusted_summaries
        ] = static::loadPreviousSummaryCalculations($earliest_transacted_time);

        [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        ] = static::initiateRawCalculations(
            $context,
            $associated_accounts,
            $associated_cash_flow_activities,
            $associated_account_hashes,
            $previous_keyed_real_raw_adjusted_summaries
        );

        [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        ] = static::consolidateRawCalculations(
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $associated_accounts,
            $associated_cash_flow_activities,
            $financial_entries,
            $financial_entry_atoms
        );

        [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows
        ] = static::makeResources(
            $associated_accounts,
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        );

        $linked_currencies = AccountModel::extractLinkedCurrencies($accounts);
        $raw_exchange_rates = CurrencyModel::makeExchangeRates(
            $latest_entry_transacted_time,
            $linked_currencies
        );

        return [
            array_values($accounts),
            array_values($associated_account_hashes),
            array_values($summaries),
            array_values($flows),
            $raw_exchange_rates
        ];
    }

    private static function loadLinkedResourcesAndFinancialEntryAtoms(
        Context $context,
        array $financial_entries
    ): array {
        $linked_modifiers = [];
        foreach ($financial_entries as $document) {
            $modifier_id = $document->modifier_id;
            array_push($linked_modifiers, $modifier_id);
        }
        $linked_modifiers = array_unique($linked_modifiers);

        ModifierCache::make($context)->loadResources($linked_modifiers);
        $modifier_atom_cache = ModifierAtomCache::make($context);
        $modifier_atom_cache->loadResourcesFromParentIDs($linked_modifiers);

        $associated_accounts = $modifier_atom_cache->extractAssociatedAccountIDs();
        $linked_accounts = array_unique(array_values($associated_accounts));
        AcccountCache::make($context)->loadResources($linked_accounts);

        $linked_modifier_atoms = array_keys($associated_accounts);
        $modifier_atom_activity_cache = ModifierAtomActivityCache::make($context);
        $modifier_atom_activity_cache->loadResources($linked_modifier_atoms);

        $associated_cash_flow_activities = $modifier_atom_activity_cache
            ->extractAssociatedCashFlowActivityIDs();
        $linked_cash_flow_activities = array_unique(array_values($associated_cash_flow_activities));

        $modifier_atom_activity_cache->extractAssociatedCashFlowActivityIDs();
        CashFlowActivityCache::make($context)->loadResources($linked_cash_flow_activities);

        $linked_financial_entries = [];
        foreach ($financial_entries as $document) {
            $id = $document->id;
            array_push($linked_financial_entries, $id);
        }

        $financial_entry_atoms = [];
        if (count($linked_financial_entries) > 0) {
            $financial_entry_atoms = model(FinancialEntryAtomModel::class)
                ->whereIn("id", array_unique($linked_financial_entries))
                ->findAll();
        }

        return [
            $associated_accounts,
            $associated_cash_flow_activities,
            $financial_entry_atoms
        ];
    }

    private static function generateAccountHashes(
        array $started_at,
        array $finished_at,
        array $associated_accounts
    ): array {
        $started_at = $started_at;
        $finished_at = $finished_at;
        $account_hashes = [];

        foreach ($associated_accounts as $account_id) {
            $account_hashes[$account_id] = [
                "account_id" => $account_id,
                "hash" => FrozenAccountModel::generateAccountHash(
                    $started_at,
                    $finished_at,
                    $account_id
                )
            ];
        }

        return $account_hashes;
    }

    private static function minMaxTransactedTimes(array $financial_entries): array
    {
        $earliest_transacted_time = null;
        $latest_transacted_time = null;
        foreach ($financial_entries as $document) {
            $transacted_time = $document->transacted_at;
            if (
                $earliest_transacted_time === null
                || $transacted_time->isBefore($earliest_transacted_time)
            ) {
                $earliest_transacted_time = $transacted_time;
            }

            if (
                $latest_transacted_time === null
                || $transacted_time->isAfter($latest_transacted_time)
            ) {
                $latest_transacted_time = $transacted_time;
            }
        }

        return [ $earliest_transacted_time, $latest_transacted_time ];
    }

    private static function loadPreviousSummaryCalculations(
        string $earliest_transacted_time
    ): array {
        $previous_frozen_period = FrozenPeriodModel::findLatestPeriod($earliest_transacted_time);

        $keyed_real_raw_adjusted_summaries = [];

        if ($previous_frozen_period) {
            $previous_frozen_accounts = model(FrozenAccountModel::class, false)
                ->where("frozen_period_id", $previous_frozen_period->id)
                ->findAll();
            $keyed_frozen_accounts = Resource::key(
                $previous_frozen_accounts,
                fn ($frozen_account) => $frozen_account->hash,
            );
            $frozen_account_hashes = array_keys($keyed_frozen_accounts);

            $previous_real_adjusted_summaries = model(
                RealAdjustedSummaryCalculationModel::class,
                false
            )->whereIn("frozen_account_hash", $frozen_account_hashes)->findAll();

            foreach ($previous_real_adjusted_summaries as $summary_calculation) {
                $frozen_account_hash = $summary_calculation->frozen_account_hash;
                $account_id = $keyed_frozen_accounts[$frozen_account_hash]->account_id;

                $keyed_real_raw_adjusted_summaries[$account_id] = [
                    "opened_amount" => $summary_calculation->closed_amount,
                    "closed_amount" => $summary_calculation->closed_amount
                ];
            }
        }

        return [
            $keyed_real_raw_adjusted_summaries
        ];
    }

    private static function initiateRawCalculations(
        Context $context,
        array $associated_accounts,
        array $associated_cash_flow_activities,
        array $associated_account_hashes,
        array $previous_keyed_real_raw_adjusted_summaries
    ): array {
        $account_cache = AccountCache::make($context);
        $keyed_real_raw_unadjusted_summaries = [];
        $keyed_real_raw_adjusted_summaries = [];
        $keyed_real_raw_flows = [];

        foreach ($associated_accounts as $modifier_atom_id => $account_id) {
            $account_hash = $associated_account_hashes[$account_id]["hash"];
            $keyed_real_raw_adjusted_summaries[$account_id] = isset(
                $previous_keyed_real_raw_adjusted_summaries[$account_id]
            ) ? $previous_keyed_real_raw_adjusted_summaries[$account_id] : [
                "opened_amount" => RationalNumber::zero(),
                "closed_amount" => RationalNumber::zero()
            ];
            $keyed_real_raw_adjusted_summaries[$account_id]["frozen_account_hash"] = $account_hash;

            $is_debited_normally = $account_cache->isDebitedNormally($account_id);
            $current_balance = $keyed_real_raw_adjusted_summaries[$account_id]["opened_amount"];

            $keyed_real_raw_unadjusted_summaries[$account_id] = [
                "frozen_account_hash" => $account_hash,
                "debit_amount" => $is_debited_normally ? $current_balance : RationalNumber::zero(),
                "credit_amount" => $is_debited_normally ? RationalNumber::zero() : $current_balance
            ];

            if (isset($associated_cash_flow_activities[$modifier_atom_id])) {
                $cash_flow_activity_id = $associated_cash_flow_activities[$modifier_atom_id];
                if (!isset($keyed_raw_flows[$cash_flow_activity_id])) {
                    $keyed_raw_flows[$cash_flow_activity_id] = [];
                }

                $keyed_real_raw_flows[$cash_flow_activity_id][$account_id] = [
                    "frozen_account_hash" => $account_hash,
                    "cash_flow_activity_id" => $cash_flow_activity_id,
                    "net_amount" => RationalNumber::zero()
                ];
            }
        }

        return [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        ];
    }

    private static function consolidateRawCalculations(
        array $keyed_real_raw_unadjusted_summaries,
        array $keyed_real_raw_adjusted_summaries,
        array $keyed_real_raw_flows,
        array $associated_accounts,
        array $associated_cash_flow_activities,
        array $financial_entries,
        array $financial_entry_atoms
    ): array {
        $account_cache = AccountCache::make($context);
        $modifier_cache = ModifierCache::make($context);
        $modifier_atom_cache = ModifierAtomCache::make($context);
        $keyed_financial_entries = Resource::key($financial_entries, fn ($entry) => $entry->id);

        foreach ($financial_entry_atoms as $financial_entry_atom) {
            $financial_entry_id = $financial_entry_atom->financial_entry_id;
            $financial_entry = $keyed_financial_entries[$financial_entry_id];
            $modifier_id = $financial_entry->modifier_id;
            $modifier_atom_id = $financial_entry_atom->modifier_atom_id;
            $account_id = $financial_entry_atom->account_id;
            $numerical_value = $financial_entry_atom->numerical_value;

            $is_debited_normally = $account_cache->isDebitedNormally($account_id);
            $adjusted_value = $is_debited_normally === (
                $atom_kind === REAL_DEBIT_MODIFIER_ATOM_KIND
                || $atom_kind === IMAGINARY_DEBIT_MODIFIER_ATOM_KIND
            ) ? $numerical_value : $numerical_value->negated();

            $keyed_real_raw_adjusted_summaries[$account_id]["closed_amount"]
                = $keyed_real_raw_adjusted_summaries[$account_id]["closed_amount"]
                    ->plus($adjusted_value);

            if ($modifier_cache->determineModifierAction($modifier_id) !== CLOSE_MODIFIER_ACTION) {
                $atom_kind = $modifier_atom_cache->determineModifierAtomKind($modifier_atom_id);
                switch ($atom_kind) {
                    case REAL_DEBIT_MODIFIER_ATOM_KIND: {
                        $keyed_real_raw_unadjusted_summaries["debit_amount"]
                            = $keyed_real_raw_unadjusted_summaries["debit_amount"]
                                ->plus($numerical_value);
                        break;
                    }
                    case REAL_CREDIT_MODIFIER_ATOM_KIND: {
                        $keyed_real_raw_unadjusted_summaries["credit_amount"]
                            = $keyed_real_raw_unadjusted_summaries["credit_amount"]
                                ->plus($numerical_value);
                        break;
                    }
                        // TODO: Implement calculation for imaginary values.
                    case IMAGINARY_DEBIT_MODIFIER_ATOM_KIND: {
                        break;
                    }
                    case IMAGINARY_CREDIT_MODIFIER_ATOM_KIND: {
                        break;
                    }
                    case ITEM_COUNT_MODIFIER_ATOM_KIND: {
                        break;
                    }
                    case PRICE_MODIFIER_ATOM_KIND: {
                        break;
                    }
                }

                if (isset($associated_cash_flow_activities[$modifier_atom_id])) {
                    $keyed_real_raw_flows[$cash_flow_activity_id][$account]["net_amount"]
                        = $keyed_real_raw_flows[$cash_flow_activity_id][$account]["net_amount"]
                            ->plus($adjusted_value);
                }
            }
        }

        return [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        ];
    }

    private static function makeResources(
        array $associated_accounts,
        array $keyed_real_raw_unadjusted_summaries,
        array $keyed_real_raw_adjusted_summaries,
        array $keyed_real_raw_flows
    ): array {
        $frozen_accounts = $associated_accounts;

        $keyed_real_raw_unadjusted_summaries = array_filter(
            $keyed_real_raw_unadjusted_summaries,
            function ($real_raw_unadjusted_summary) {
                return $real_raw_unadjusted_summary["debit_amount"]->getSign() !== 0
                    || $real_raw_unadjusted_summary["credit_amount"]->getSign() !== 0;
            }
        );
        $real_unadjusted_summaries = array_map(
            function ($raw_calculation) {
                $raw_calculation["debit_amount"] = $raw_calculation["debit_amount"]->simplified();
                $raw_calculation["credit_amount"] = $raw_calculation["credit_amount"]->simplified();

                $raw_calculation = (new RealUnadjustedSummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            $keyed_real_raw_unadjusted_summaries
        );

        $keyed_real_raw_adjusted_summaries = array_filter(
            $keyed_real_raw_adjusted_summaries,
            function ($real_raw_adjusted_summary) {
                return $real_raw_adjusted_summary["opened_amount"]->getSign() !== 0
                    || $real_raw_adjusted_summary["closed_amount"]->getSign() !== 0;
            }
        );
        $real_adjusted_summaries = array_map(
            function ($raw_calculation, $account_id) {
                $raw_calculation["opened_amount"] = $raw_calculation["opened_amount"]->simplified();
                $raw_calculation["closed_amount"] = $raw_calculation["closed_amount"]->simplified();

                $raw_calculation = (new RealAdjustedSummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            $keyed_real_raw_adjusted_summaries,
            array_keys($keyed_real_raw_adjusted_summaries)
        );

        $real_flows = [];
        foreach ($keyed_real_flows as $cash_flow_activity_id => $real_flows_per_activity) {
            foreach ($real_flows_per_activity as $account_id => $real_flow) {
                if (!$real_flow["net_amount"]->isZero()) {
                    $raw_calculation = (new RealFlowCalculation())->fill([
                        "frozen_account_hash" => $real_flow["frozen_account_hash"],
                        "cash_flow_activity_id" => $cash_flow_activity_id,
                        "net_amount" => $real_flow["net_amount"]->simplified()
                    ]);

                    array_push($real_flows, $raw_calculation);
                }
            }
        }

        return [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows
        ];
    }
}
