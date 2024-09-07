<?php

namespace App\Models;

use App\Casts\ModifierAction;
use App\Casts\RationalNumber;
use App\Entities\Currency;
use App\Libraries\Resource;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class CurrencyModel extends BaseResourceModel
{
    protected $table = "currencies";
    protected $returnType = Currency::class;
    protected $allowedFields = [
        "user_id",
        "code",
        "name",
        "presentational_precision",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "code",
        "name",
        "presentational_precision",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "code"  => $faker->unique()->currencyCode(),
            "name"  => $faker->unique()->firstName(),
            "presentational_precision"  => $faker->randomElement([ 0, 1, 2, 3, 4, 12 ])
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }

    public static function makeExchangeRates(
        string $requested_time,
        array $requested_currencies
    ): array {
        $account_subquery = model(AccountModel::class, false)
            ->builder()
            ->select("id")
            ->whereIn(
                "currency_id",
                $requested_currencies
            );
        $exchange_modifiers = model(ModifierModel::class)
            ->where("action", ModifierAction::set(EXCHANGE_MODIFIER_ACTION))
            ->whereIn(
                "id",
                model(FinancialEntryModel::class, false)
                    ->builder()
                    ->select("modifier_id")
                    ->where(
                        "transacted_at <=",
                        $requested_time
                    )
            )
            ->whereIn("debit_account_id", $account_subquery)
            ->whereIn("credit_account_id", $account_subquery)
            ->withDeleted()
            ->findAll();

        $linked_exchange_accounts = [];
        foreach ($exchange_modifiers as $modifier) {
            $debit_account_id = $modifier->debit_account_id;
            $credit_account_id = $modifier->credit_account_id;
            array_push($linked_exchange_accounts, $debit_account_id, $credit_account_id);
        }

        $exchange_accounts = [];
        if (count($linked_exchange_accounts) > 0) {
            $exchange_accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_exchange_accounts))
                ->withDeleted()
                ->findAll();
        }

        $financial_entries = count($exchange_modifiers) > 0
            ? model(FinancialEntryModel::class)
                ->whereIn("modifier_id", array_map(
                    function ($modifier) {
                        return $modifier->id;
                    },
                    $exchange_modifiers
                ))
                ->orderBy("transacted_at", "DESC")
                ->withDeleted()
                ->findAll()
            : [];

        if (count($financial_entries) === 0) {
            return [];
        }

        $grouped_financial_entries = Resource::group(
            $financial_entries,
            function ($financial_entry) {
                return $financial_entry->modifier_id;
            }
        );

        $raw_exchange_rates = [];

        $exchange_modifiers = array_map(
            function ($exchange_modifier) use ($exchange_accounts) {
                $debit_account = array_values(array_filter(
                    $exchange_accounts,
                    function ($account) use ($exchange_modifier) {
                        return $account->id === $exchange_modifier->debit_account_id;
                    }
                ))[0];
                $credit_account = array_values(array_filter(
                    $exchange_accounts,
                    function ($account) use ($exchange_modifier) {
                        return $account->id === $exchange_modifier->credit_account_id;
                    }
                ))[0];

                return [
                    "id" => $exchange_modifier->id,
                    "debit_account" => $debit_account,
                    "credit_account" => $credit_account
                ];
            },
            $exchange_modifiers
        );
        $raw_exchange_entries = array_reduce(
            $exchange_modifiers,
            function ($raw_entries, $modifier) use ($grouped_financial_entries) {
                $financial_entries = $grouped_financial_entries[$modifier["id"]];

                foreach ($financial_entries as $financial_entry) {
                    if (isset($raw_entries[$modifier["id"]])) {
                        if (
                            $financial_entry
                            ->updated_at
                            ->isAfter($raw_entries[$modifier["id"]]->updated_at)
                        ) {
                            $raw_entries[$modifier["id"]] = $financial_entry;
                        }
                    } else {
                        $raw_entries[$modifier["id"]] = $financial_entry;
                    }
                }

                return $raw_entries;
            },
            []
        );
        $raw_exchange_rates = array_reduce(
            $exchange_modifiers,
            function ($raw_exchanges, $modifier) use ($raw_exchange_entries) {
                $financial_entry = $raw_exchange_entries[$modifier["id"]];
                $debit_account = $modifier["debit_account"];
                $credit_account = $modifier["credit_account"];
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

                $exchange_id = $source_currency_id."_".$destination_currency_id;

                $source_value = $may_use_debit_account_as_destination
                    ? $credit_value
                    : $debit_value;
                $destination_value = $may_use_debit_account_as_destination
                    ? $debit_value
                    : $credit_value;

                if (isset($raw_exchanges[$exchange_id])) {
                    if (
                        $financial_entry
                            ->updated_at
                            ->isAfter($raw_exchanges[$exchange_id]["updated_at"])
                    ) {
                        $raw_exchanges[$exchange_id]["source"]["value"] = $source_value;
                        $raw_exchanges[$exchange_id]["destination"]["value"] = $destination_value;
                    }
                } else {
                    $raw_exchanges[$exchange_id] = [
                        "source" => [
                            "currency_id" => $source_currency_id,
                            "value" => $source_value
                        ],
                        "destination" => [
                            "currency_id" => $destination_currency_id,
                            "value" => $destination_value
                        ],
                        "updated_at" => $financial_entry->updated_at->toDateTimeString()
                    ];
                }

                return $raw_exchanges;
            },
            []
        );
        $raw_exchange_rates = array_values($raw_exchange_rates);
        $raw_exchange_rates = array_map(
            function ($raw_exchange_rate) {
                $source = RationalNumber::get($raw_exchange_rate["source"]["value"]);
                $destination = RationalNumber::get($raw_exchange_rate["destination"]["value"]);
                $rate = $destination->dividedBy($source)->simplified();

                $raw_exchange_rate["source"]["value"] = $rate->getDenominator();
                $raw_exchange_rate["destination"]["value"] = $rate->getNumerator();
                return $raw_exchange_rate;
            },
            $raw_exchange_rates
        );

        return $raw_exchange_rates;
    }
}
