<?php

namespace App\Controllers;

use App\Casts\RationalNumber;
use App\Contracts\OwnedResource;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Exceptions\UnprocessableRequest;
use App\Libraries\FinancialStatementGroup;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;
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

    protected static function enrichResponseDocument(array $initial_document): array
    {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);

        if (!$is_single_main_document) {
            return $enriched_document;
        }

        $summary_calculations = model(SummaryCalculationModel::class)
            ->where("frozen_period_id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["summary_calculations"] = $summary_calculations;

        $flow_calculations = model(FlowCalculationModel::class)
            ->where("frozen_period_id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["flow_calculations"] = $flow_calculations;

        $linked_accounts = SummaryCalculationModel::extractLinkedAccounts($summary_calculations);
        $accounts = model(AccountModel::class)->selectUsingMultipleIDs($linked_accounts);
        $enriched_document["accounts"] = $accounts;

        $linked_currencies = AccountModel::extractLinkedCurrencies($accounts);
        $currencies = model(CurrencyModel::class)->selectUsingMultipleIDs($linked_currencies);
        $enriched_document["currencies"] = $currencies;

        $linked_cash_flow_activities = FlowCalculationModel::extractLinkedCashFlowActivities(
            $flow_calculations
        );
        $cash_flow_activities = model(CashFlowActivityModel::class)->selectUsingMultipleIDs(
            $linked_cash_flow_activities
        );
        $enriched_document["cash_flow_activities"] = $cash_flow_activities;

        $raw_exchange_rates = CurrencyModel::makeExchangeRates(
            $initial_document[static::getIndividualName()]->finished_at,
            $linked_currencies
        );

        $enriched_document["@meta"] = [
            "statements" => static::makeStatements(
                $currencies,
                $accounts,
                $summary_calculations,
                $flow_calculations
            ),
            "exchange_rates" => $raw_exchange_rates
        ];

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document): array
    {
        $main_document = $created_document[static::getIndividualName()];

        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations
        ] = static::calculateValidSummaryCalculations($main_document, true);

        $raw_summary_calculations = array_map(
            function ($raw_summary_calculation) use ($main_document) {
                return array_merge(
                    [ "frozen_period_id" => $main_document["id"] ],
                    $raw_summary_calculation->toArray()
                );
            },
            $raw_summary_calculations
        );

        model(SummaryCalculationModel::class)->insertBatch($raw_summary_calculations);

        $raw_flow_calculations = array_map(
            function ($raw_flow_calculation) use ($main_document) {
                return array_merge(
                    [ "frozen_period_id" => $main_document["id"] ],
                    $raw_flow_calculation->toArray()
                );
            },
            $raw_flow_calculations
        );

        model(FlowCalculationModel::class)->insertBatch($raw_flow_calculations);

        return $created_document;
    }

    private static function calculateValidSummaryCalculations(
        array $main_document,
        bool $must_be_strict
    ): array {
        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
        ] = FrozenPeriodModel::makeRawCalculations(
            $main_document["started_at"],
            $main_document["finished_at"]
        );
        $keyed_calculations = Resource::key($raw_summary_calculations, function ($calculation) {
            return $calculation->account_id;
        });

        if ($must_be_strict) {
            foreach ($accounts as $account) {
                if (
                    (
                        $account->kind === EXPENSE_ACCOUNT_KIND
                        || $account->kind === INCOME_ACCOUNT_KIND
                    )
                    // Some accounts are temporary and exist only for closing other accounts.
                    // Therefore, they would not have any summary calculations.
                    && isset($keyed_calculations[$account->id])
                ) {
                    $raw_calculation = $keyed_calculations[$account->id];
                    if (
                        !(
                            $raw_calculation->closed_debit_amount->getSign() === 0
                            && $raw_calculation->closed_debit_amount->getSign() === 0
                        )
                    ) {
                        throw new UnprocessableRequest(
                            "Temporary accounts must be closed first to create the frozen period."
                        );
                    }
                }
            }
        }

        return [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
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
                    [
                        $cash_flow_activities,
                        $accounts,
                        $raw_summary_calculations,
                        $raw_flow_calculations,
                        $raw_exchange_rates
                    ] = static::calculateValidSummaryCalculations(
                        $info,
                        false
                    );

                    $linked_currencies = AccountModel::extractLinkedCurrencies($accounts);
                    $currencies = model(CurrencyModel::class)
                        ->selectUsingMultipleIDs($linked_currencies);
                    $statements = static::makeStatements(
                        $currencies,
                        $accounts,
                        $raw_summary_calculations,
                        $raw_flow_calculations
                    );

                    $response_document = [
                        "@meta" => [
                            "statements" => $statements,
                            "exchange_rates" => $raw_exchange_rates
                        ],
                        static::getIndividualName() => $info,
                        "summary_calculations" => $raw_summary_calculations,
                        "accounts" => $accounts,
                        "currencies" => $currencies,
                        "cash_flow_activities" => $cash_flow_activities,
                        "flow_calculations" => $raw_flow_calculations
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
        array $summary_calculations,
        array $flow_calculations
    ): array {
        $financial_statement_group = new FinancialStatementGroup(
            $accounts,
            $summary_calculations,
            $flow_calculations,
            new ExchangeRateDerivator([])
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

                array_push($statements, $statement_set);

                return $statements;
            },
            []
        );

        return $statements;
    }
}
