<?php

namespace App\Controllers;

use App\Casts\RationalNumber;
use App\Contracts\OwnedResource;
use App\Exceptions\UnprocessableRequest;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CashFlowActivityCache;
use App\Libraries\Context\CurrencyCache;
use App\Libraries\Context\ModifierAtomActivityCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\FinancialStatementGroup;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FrozenAccountModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use App\Models\RealFlowCalculationModel;
use App\Models\RealUnadjustedSummaryCalculationModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class FrozenPeriodController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "frozen_period";
    }

    protected static function getCollectiveName(): string
    {
        return "frozen_periods";
    }

    protected static function getModelName(): string
    {
        return FrozenPeriodModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        return static::makeValidation();
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        return static::makeValidation();
    }

    protected static function mustTransactForCreation(): bool
    {
        return true;
    }

    protected static function enrichResponseDocument(
        array $initial_document,
        array $relationships
    ): array {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);

        if (!$is_single_main_document) {
            return $enriched_document;
        }

        $frozen_accounts = model(FrozenAccountModel::class)
            ->where("frozen_period_id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["frozen_accounts"] = $frozen_accounts;

        $frozen_account_hashes = array_map(fn ($account) => $account->hash, $frozen_accounts);

        $real_unadjusted_summaries = model(RealUnadjustedSummaryCalculationModel::class)
            ->whereIn("frozen_account_hash", $frozen_account_hashes)
            ->findAll();
        $enriched_document["real_unadjusted_summary_calculations"] = $real_unadjusted_summaries;

        $real_adjusted_summaries = model(RealAdjustedSummaryCalculationModel::class)
            ->whereIn("frozen_account_hash", $frozen_account_hashes)
            ->findAll();
        $enriched_document["real_adjusted_summary_calculations"] = $real_adjusted_summaries;

        $real_flows = model(RealFlowCalculationModel::class)
            ->whereIn("frozen_account_hash", $frozen_account_hashes)
            ->findAll();
        $enriched_document["real_flow_calculations"] = $real_flows;

        $context = Context::make();
        $account_cache = AccountCache::make($context);
        $linked_accounts = array_unique(
            FrozenAccountModel::extractLinkedAccounts($frozen_accounts)
        );
        $account_cache->loadResources($linked_accounts);
        $accounts = array_map(
            fn ($account_id) => $account_cache->getLoadedResource($account_id),
            $linked_accounts
        );
        if (in_array("*", $relationships) || in_array("accounts", $relationships)) {
            $enriched_document["accounts"] = $accounts;
        }

        $currency_cache = CurrencyCache::make($context);
        $linked_currencies = array_unique(AccountModel::extractLinkedCurrencies($accounts));
        $currency_cache->loadResources($linked_currencies);
        $currencies = array_map(
            fn ($currency_id) => $currency_cache->getLoadedResource($currency_id),
            $linked_currencies
        );
        if (in_array("*", $relationships) || in_array("currencies", $relationships)) {
            $enriched_document["currencies"] = $currencies;
        }

        if (in_array("*", $relationships) || in_array("precision_formats", $relationships)) {
            [
                $precision_formats
            ] = CurrencyModel::selectAncestorsWithResolvedResources($currencies);

            $enriched_document["precision_formats"] = $precision_formats;
        }

        if (in_array("*", $relationships) || in_array("cash_flow_activities", $relationships)) {
            $cash_flow_activity_cache = CashFlowActivityCache::make($context);
            $linked_cash_flow_activities
                = array_unique(RealFlowCalculationModel::extractLinkedCashFlowActivities($real_flows));
            $cash_flow_activity_cache->loadResources($linked_cash_flow_activities);
            $enriched_document["cash_flow_activities"] = array_map(
                fn ($cash_flow_activity_id) => $cash_flow_activity_cache->getLoadedResource(
                    $cash_flow_activity_id
                ),
                $linked_cash_flow_activities
            );
        }

        $raw_exchange_rates = [];
        $exchange_rate_cache = ExchangeRateCache::make($context);
        $finished_at = $initial_document[static::getIndividualName()]->finished_at;
        $exchange_rate_cache->setLastExchangeRateTimeOnce($finished_at);
        $derivator = $exchange_rate_cache->buildDerivator($finished_at);
        $raw_exchange_rates = $derivator->exportExchangeRates();

        $enriched_document["@meta"] = [
            "statements" => static::makeStatements(
                $currencies,
                $accounts,
                $frozen_accounts,
                $real_unadjusted_summaries,
                $real_adjusted_summaries,
                $real_flows,
                $derivator
            ),
            "exchange_rates" => $raw_exchange_rates
        ];

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document, array $input): array
    {
        $main_document = $created_document[static::getIndividualName()];

        $context = Context::make();
        [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows
        ] = static::calculateValidCalculations($context, $main_document, true);

        if (count($frozen_accounts) > 0) {
            foreach ($frozen_accounts as $frozen_account) {
                $frozen_account->frozen_period_id = $main_document["id"];
            }
            model(FrozenAccountModel::class)->insertBatch($frozen_accounts);

            model(RealFlowCalculationModel::class)->insertBatch($real_flows);
            model(RealAdjustedSummaryCalculationModel::class)
                ->insertBatch($real_adjusted_summaries);
            model(RealUnadjustedSummaryCalculationModel::class)
                ->insertBatch($real_unadjusted_summaries);
        }

        return $created_document;
    }

    private static function calculateValidCalculations(
        Context $context,
        array $main_document,
        bool $must_be_strict
    ): array {
        $account_cache = AccountCache::make($context);
        $current_user = auth()->user();

        [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::makeRawCalculations(
            $current_user,
            $context,
            $main_document["started_at"],
            $main_document["finished_at"]
        );

        $keyed_frozen_accounts = Resource::key(
            $frozen_accounts,
            fn ($frozen_account) => $frozen_account->hash
        );

        if ($must_be_strict) {
            foreach ($real_adjusted_summaries as $adjusted_summary) {
                $frozen_account_hash = $adjusted_summary->frozen_account_hash;
                $account_id = $keyed_frozen_accounts[$frozen_account_hash]->account_id;
                $account_kind = $account_cache->determineAccountKind($account_id);

                if (
                    $account_kind === GENERAL_EXPENSE_ACCOUNT_KIND
                    || $account_kind === GENERAL_REVENUE_ACCOUNT_KIND
                    || $account_kind === GENERAL_TEMPORARY_ACCOUNT_KIND
                    || $account_kind === DIRECT_COST_ACCOUNT_KIND
                    || $account_kind === DIRECT_SALE_ACCOUNT_KIND
                ) {
                    throw new UnprocessableRequest(
                        "Temporary accounts must be closed first to create the frozen period."
                    );
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

    private static function generateFullValidCalculations(
        Context $context,
        array $main_document,
        bool $must_be_strict
    ): array {
        [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows
        ] = static::calculateValidCalculations($context, $main_document, $must_be_strict);

        $account_cache = AccountCache::make($context);
        $accounts = array_filter(array_map(
            fn ($frozen_info) => $account_cache->getLoadedResource($frozen_info->account_id),
            $frozen_accounts
        ), fn ($account) => !is_null($account));

        $currency_cache = CurrencyCache::make($context);
        $currency_IDs = array_map(
            fn ($account) => $account->currency_id,
            $accounts
        );
        $currency_cache->loadResources(array_unique($currency_IDs));
        $currencies = array_map(fn ($id) => $currency_cache->getLoadedResource($id), $currency_IDs);

        $exchange_rate_cache = ExchangeRateCache::make($context);
        $last_known_time = Time::parse($main_document["finished_at"]);
        $exchange_rate_cache->setLastExchangeRateTimeOnce($last_known_time);
        $derivator = $exchange_rate_cache->buildDerivator($last_known_time);

        [
            $precision_formats
        ] = CurrencyModel::selectAncestorsWithResolvedResources($currencies);

        return [
            $precision_formats,
            $currencies,
            $accounts,
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows,
            $derivator
        ];
    }

    public function dry_run_create()
    {
        helper("auth");

        $current_user = auth()->user();
        $controller = $this;
        $validation = $this->makeCreateValidation($current_user);
        return $this
            ->useValidInputsOnly(
                $validation,
                function ($request_data) use ($controller, $current_user) {
                    $model = static::getModel();
                    $info = static::prepareRequestData($request_data);
                    $context = Context::make();

                    [
                        $precision_formats,
                        $currencies,
                        $accounts,
                        $frozen_accounts,
                        $real_unadjusted_summaries,
                        $real_adjusted_summaries,
                        $real_flows,
                        $derivator
                    ] = static::generateFullValidCalculations(
                        $context,
                        $info,
                        false
                    );

                    $statements = static::makeStatements(
                        $currencies,
                        $accounts,
                        $frozen_accounts,
                        $real_unadjusted_summaries,
                        $real_adjusted_summaries,
                        $real_flows,
                        $derivator
                    );

                    $modifier_atom_activity_cache = ModifierAtomActivityCache::make($context);
                    $associated_cash_flow_activities = $modifier_atom_activity_cache
                        ->extractAssociatedCashFlowActivityIDs();
                    $cash_flow_activity_IDs = array_unique(array_values(
                        $associated_cash_flow_activities
                    ));
                    $cash_flow_activities = model(CashFlowActivityModel::class)
                        ->whereIn("id", $cash_flow_activity_IDs)
                        ->findAll();

                    $response_document = [
                        "@meta" => [
                            "statements" => $statements,
                            "exchange_rates" => $derivator->exportExchangeRates()
                        ],
                        static::getIndividualName() => $info,
                        "frozen_accounts" => $frozen_accounts,
                        "real_unadjusted_summary_calculations" => $real_unadjusted_summaries,
                        "real_adjusted_summary_calculations" => $real_adjusted_summaries,
                        "real_flow_calculations" => $real_flows,
                        "accounts" => $accounts,
                        "currencies" => $currencies,
                        "cash_flow_activities" => $cash_flow_activities,
                        "precision_formats" => $precision_formats
                    ];

                    return $controller->response->setJSON($response_document);
                }
            );
    }

    public function recalculate()
    {
        helper("auth");

        $current_user = auth()->user();
        $controller = $this;
        $validation = $this->makeRecalculationValidation();
        return $this
            ->useValidInputsOnly(
                $validation,
                function ($request_data) use ($controller, $current_user) {
                    $model = static::getModel();
                    $info = static::prepareRequestData($request_data);
                    $context = Context::make();

                    [
                        $precision_formats,
                        $currencies,
                        $accounts,
                        $frozen_accounts,
                        $real_unadjusted_summaries,
                        $real_adjusted_summaries,
                        $real_flows,
                        $derivator
                    ] = static::generateFullValidCalculations(
                        $context,
                        $info,
                        false
                    );

                    $linked_currencies = AccountModel::extractLinkedCurrencies($accounts);
                    if (isset($info["source_currency_id"])) {
                        $linked_currencies = [ ...$linked_currencies, $info["source_currency_id"] ];
                    }
                    $linked_currencies = [ ...$linked_currencies, $info["target_currency_id"] ];
                    $currencies = model(CurrencyModel::class)
                        ->selectUsingMultipleIDs(array_unique($linked_currencies));

                    $financial_statement_group = new FinancialStatementGroup(
                        $accounts,
                        $frozen_accounts,
                        $real_unadjusted_summaries,
                        $real_adjusted_summaries,
                        $real_flows,
                        $derivator
                    );

                    $keyed_currencies = Resource::key($currencies, function ($currency) {
                        return $currency->id;
                    });
                    $source_currency = isset($info["source_currency_id"])
                        ? $keyed_currencies[$info["source_currency_id"]]
                        : null;
                    $target_currency = $keyed_currencies[$info["target_currency_id"]];

                    $statement = $financial_statement_group
                        ->generateFinancialStatements($source_currency, $target_currency);

                    $response_document = [
                        "@meta" => [
                            "statement" => $statement
                        ]
                    ];

                    return $controller->response->setJSON($response_document);
                }
            );
    }

    protected static function prepareRequestData(array $raw_request_data): array
    {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "frozen period", [
            "required"
        ]);
        $validation->setRule("$individual_name.started_at", "start date", [
            "required",
            "valid_date[".DATE_TIME_STRING_FORMAT."]",
            "must_be_on_before_time_of_other_field[$individual_name.finished_at]"
        ]);
        $validation->setRule("$individual_name.finished_at", "finish date", [
            "required",
            "valid_date[".DATE_TIME_STRING_FORMAT."]",
            "must_be_before_incoming_midnight"
        ]);

        return $validation;
    }

    private static function makeStatements(
        array $currencies,
        array $accounts,
        array $frozen_accounts,
        array $real_unadjusted_summaries,
        array $real_adjusted_summaries,
        array $real_flows,
        ExchangeRateDerivator $derivator
    ): array {
        $financial_statement_group = new FinancialStatementGroup(
            $accounts,
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows,
            $derivator
        );

        $statements = array_reduce(
            $currencies,
            function ($statements, $currency) use ($financial_statement_group) {
                $statement_set = $financial_statement_group
                    ->generateFinancialStatements($currency, $currency);

                if ($statement_set === null) {
                    // Include currencies only used in statements
                    return $statements;
                }

                return [
                    ...$statements,
                    $statement_set
                ];
            },
            []
        );

        return $statements;
    }

    private static function makeRecalculationValidation(): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.source_currency_id", "source currency", [
            "permit_empty",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                CurrencyModel::class,
                SEARCH_WITH_DELETED
            ])."]"
        ]);
        $validation->setRule("$individual_name.target_currency_id", "target currency", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                CurrencyModel::class,
                SEARCH_WITH_DELETED
            ])."]"
        ]);

        return $validation;
    }
}
