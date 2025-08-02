<?php

namespace App\Models;

use App\Entities\Account;
use App\Entities\FrozenPeriod;
use App\Entities\Modifier;
use App\Entities\ModifierAtom;
use App\Entities\FinancialEntryAtom;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel as BaseUserModel;

class UserModel extends BaseUserModel
{
    public static function makeInitialData(User $user)
    {
        $user_id = $user->id;

        $precision_format_model = model(PrecisionFormatModel::class);
        $precision_format_model->insert([
            "user_id" => $user_id,
            "name" => "Fiat Precision",
            "minimum_presentational_precision" => 0,
            "maximum_presentational_precision" => 2
        ]);
        $fiat_precision_format_id = $precision_format_model->getInsertID();

        $currency_model = model(CurrencyModel::class);
        $currency_model->insert([
            "precision_format_id" => $fiat_precision_format_id,
            "code" => "PHP",
            "name" => "Philippine Peso"
        ]);
        $peso_currency_id = $currency_model->getInsertID();

        $cash_flow_activity_model = model(CashFlowActivityModel::class);
        $cash_flow_activity_model->insert([
            "user_id" => $user_id,
            "name" => "Operating Activities",
            "description" => "Activities that are part or related to your normal life."
        ]);
        $operating_cash_flow_activity_id = $cash_flow_activity_model->getInsertID();

        $cash_flow_activity_model->insert([
            "user_id" => $user_id,
            "name" => "Financing Activities",
            "description" => "Activities that are part or related to your loans."
        ]);
        $financing_cash_flow_activity_id = $cash_flow_activity_model->getInsertID();

        $account_model = model(AccountModel::class);
        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Cash",
            "description" => "This is an example account.",
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ]));
        $cash_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Fare",
            "description" => "This is an example account.",
            "kind" => GENERAL_EXPENSE_ACCOUNT_KIND
        ]));
        $fare_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Food and Beverage",
            "description" => "This is an example account.",
            "kind" => GENERAL_EXPENSE_ACCOUNT_KIND
        ]));
        $food_and_beverage_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Living Equity",
            "description" => "This is an example account.",
            "kind" => EQUITY_ACCOUNT_KIND
        ]));
        $living_equity_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Accounts Payable to Friend",
            "description" => "This is an example account.",
            "kind" => LIABILITY_ACCOUNT_KIND
        ]));
        $accounts_payable_to_friend_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Service Income",
            "description" => "This is an example account.",
            "kind" => GENERAL_REVENUE_ACCOUNT_KIND
        ]));
        $service_income_account_id = $account_model->getInsertID();

        $account_model->insert(new Account([
            "currency_id" => $peso_currency_id,
            "name" => "Revenue and Expenses",
            "description" => "This is an example account.",
            "kind" => GENERAL_TEMPORARY_ACCOUNT_KIND
        ]));
        $closing_account_id = $account_model->getInsertID();

        $modifier_model = model(ModifierModel::class);
        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Record existing balance",
            "description" => "This is an example modifier.",
            "action" => RECORD_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $record_existing_balance_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Borrow cash from a friend",
            "description" => "This is an example modifier.",
            "action" => RECORD_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $borrow_cash_from_a_friend_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Pay fare",
            "description" => "This is an example modifier.",
            "action" => RECORD_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $pay_fare_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Buy food and beverage",
            "description" => "This is an example modifier.",
            "action" => RECORD_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $buy_food_and_beverage_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Collect service income",
            "description" => "This is an example modifier.",
            "action" => RECORD_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $collect_service_income_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Close service income",
            "description" => "This is an example modifier.",
            "action" => CLOSE_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $close_service_income_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Close fare",
            "description" => "This is an example modifier.",
            "action" => CLOSE_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $close_fare_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Close food and beverage",
            "description" => "This is an example modifier.",
            "action" => CLOSE_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $close_food_and_beverage_modifier_id = $modifier_model->getInsertID();

        $modifier_model->insert(new Modifier([
            "user_id" => $user_id,
            "name" => "Close remaining balance",
            "description" => "This is an example modifier.",
            "action" => CLOSE_MODIFIER_ACTION,
            "kind" => MANUAL_MODIFIER_KIND
        ]));
        $close_remaining_balance_modifier_id = $modifier_model->getInsertID();

        $modifier_atom_model = model(ModifierAtomModel::class);
        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $record_existing_balance_modifier_id,
            "account_id" => $cash_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $record_existing_balance_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $record_existing_balance_modifier_id,
            "account_id" => $living_equity_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $record_existing_balance_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $borrow_cash_from_a_friend_modifier_id,
            "account_id" => $cash_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $borrow_cash_from_a_friend_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $borrow_cash_from_a_friend_modifier_id,
            "account_id" => $accounts_payable_to_friend_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $borrow_cash_from_a_friend_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $pay_fare_modifier_id,
            "account_id" => $fare_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $pay_fare_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $pay_fare_modifier_id,
            "account_id" => $cash_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $pay_fare_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $buy_food_and_beverage_modifier_id,
            "account_id" => $food_and_beverage_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $buy_food_and_beverage_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $buy_food_and_beverage_modifier_id,
            "account_id" => $cash_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $buy_food_and_beverage_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $collect_service_income_modifier_id,
            "account_id" => $cash_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $collect_service_income_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $collect_service_income_modifier_id,
            "account_id" => $service_income_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $collect_service_income_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_service_income_modifier_id,
            "account_id" => $service_income_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $close_service_income_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_service_income_modifier_id,
            "account_id" => $closing_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $close_service_income_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_fare_modifier_id,
            "account_id" => $closing_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $close_fare_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_fare_modifier_id,
            "account_id" => $fare_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $close_fare_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_food_and_beverage_modifier_id,
            "account_id" => $closing_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $close_food_and_beverage_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_food_and_beverage_modifier_id,
            "account_id" => $food_and_beverage_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $close_food_and_beverage_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_remaining_balance_modifier_id,
            "account_id" => $closing_account_id,
            "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
        ]));
        $close_remaining_balance_modifier_debit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_model->insert(new ModifierAtom([
            "modifier_id" => $close_remaining_balance_modifier_id,
            "account_id" => $living_equity_account_id,
            "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
        ]));
        $close_remaining_balance_modifier_credit_atom_id = $modifier_atom_model->getInsertID();

        $modifier_atom_activity_model = model(ModifierAtomActivityModel::class);
        $modifier_atom_activity_model->insert([
            "modifier_atom_id" => $record_existing_balance_modifier_credit_atom_id,
            "cash_flow_activity_id" => $operating_cash_flow_activity_id
        ]);

        $modifier_atom_activity_model->insert([
            "modifier_atom_id" => $borrow_cash_from_a_friend_modifier_credit_atom_id,
            "cash_flow_activity_id" => $financing_cash_flow_activity_id
        ]);

        $modifier_atom_activity_model->insert([
            "modifier_atom_id" => $pay_fare_modifier_debit_atom_id,
            "cash_flow_activity_id" => $operating_cash_flow_activity_id
        ]);

        $modifier_atom_activity_model->insert([
            "modifier_atom_id" => $buy_food_and_beverage_modifier_debit_atom_id,
            "cash_flow_activity_id" => $operating_cash_flow_activity_id
        ]);

        $modifier_atom_activity_model->insert([
            "modifier_atom_id" => $collect_service_income_modifier_credit_atom_id,
            "cash_flow_activity_id" => $operating_cash_flow_activity_id
        ]);

        $last_one_month = Time::today()->setDay(1)->subMonths(1);
        $financial_entry_model = model(FinancialEntryModel::class);
        $financial_entry_atom_model = model(FinancialEntryAtomModel::class);
        $financial_entry_model->insert([
            "modifier_id" => $record_existing_balance_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $first_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $first_entry_id,
            "modifier_atom_id" => $record_existing_balance_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1000"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $first_entry_id,
            "modifier_atom_id" => $record_existing_balance_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1000"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $borrow_cash_from_a_friend_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $second_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $second_entry_id,
            "modifier_atom_id" => $borrow_cash_from_a_friend_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1000"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $second_entry_id,
            "modifier_atom_id" => $borrow_cash_from_a_friend_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1000"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $pay_fare_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $third_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $third_entry_id,
            "modifier_atom_id" => $pay_fare_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "100"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $third_entry_id,
            "modifier_atom_id" => $pay_fare_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "100"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $buy_food_and_beverage_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $fourth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $fourth_entry_id,
            "modifier_atom_id" => $buy_food_and_beverage_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "500"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $fourth_entry_id,
            "modifier_atom_id" => $buy_food_and_beverage_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "500"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $collect_service_income_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $fifth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $fifth_entry_id,
            "modifier_atom_id" => $collect_service_income_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "2000"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $fifth_entry_id,
            "modifier_atom_id" => $collect_service_income_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "2000"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $close_service_income_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $sixth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $sixth_entry_id,
            "modifier_atom_id" => $close_service_income_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "2000"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $sixth_entry_id,
            "modifier_atom_id" => $close_service_income_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "2000"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $close_fare_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $seventh_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $seventh_entry_id,
            "modifier_atom_id" => $close_fare_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "100"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $seventh_entry_id,
            "modifier_atom_id" => $close_fare_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "100"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $close_food_and_beverage_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $eighth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $eighth_entry_id,
            "modifier_atom_id" => $close_food_and_beverage_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "500"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $eighth_entry_id,
            "modifier_atom_id" => $close_food_and_beverage_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "500"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $close_remaining_balance_modifier_id,
            "transacted_at" => $last_one_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $ninth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $ninth_entry_id,
            "modifier_atom_id" => $close_remaining_balance_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1400"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $ninth_entry_id,
            "modifier_atom_id" => $close_remaining_balance_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1400"
        ]));

        $frozen_period_model = model(FrozenPeriodModel::class);
        $first_frozen_period = new FrozenPeriod([
            "user_id" => $user->id,
            "started_at" => $last_one_month->toDateTimeString(),
            "finished_at" => $last_one_month->addMonths(1)->subDays(1)->toDateTimeString()
        ]);
        $frozen_period_model->insert($first_frozen_period);
        $first_frozen_period_id = $frozen_period_model->getInsertID();

        $context = Context::make();
        $context->setVariable(ContextKeys::USER, $user);

        [
            $periodic_frozen_accounts,
            $periodic_real_unadjusted_summaries,
            $periodic_real_adjusted_summaries,
            $periodic_real_flows
        ] = FrozenPeriodModel::makeRawCalculations(
            $user,
            $context,
            $last_one_month->toDateTimeString(),
            $last_one_month->addMonths(1)->subDays(1)->toDateTimeString()
        );

        foreach ($periodic_frozen_accounts as $frozen_account) {
            $frozen_account->frozen_period_id = $first_frozen_period_id;
        }

        model(FrozenAccountModel::class)->insertBatch($periodic_frozen_accounts);
        model(RealAdjustedSummaryCalculationModel::class)
            ->insertBatch($periodic_real_adjusted_summaries);
        model(RealUnadjustedSummaryCalculationModel::class)
            ->insertBatch($periodic_real_unadjusted_summaries);
        if (count($periodic_real_flows) > 0) {
            model(RealFlowCalculationModel::class)->insertBatch($periodic_real_flows);
        }

        $this_month = Time::today()->setDay(2);
        $financial_entry_model->insert([
            "modifier_id" => $pay_fare_modifier_id,
            "transacted_at" => $this_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $tenth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $tenth_entry_id,
            "modifier_atom_id" => $pay_fare_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "200"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $tenth_entry_id,
            "modifier_atom_id" => $pay_fare_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "200"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $buy_food_and_beverage_modifier_id,
            "transacted_at" => $this_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $eleventh_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $eleventh_entry_id,
            "modifier_atom_id" => $buy_food_and_beverage_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "600"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $eleventh_entry_id,
            "modifier_atom_id" => $buy_food_and_beverage_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "600"
        ]));

        $financial_entry_model->insert([
            "modifier_id" => $collect_service_income_modifier_id,
            "transacted_at" => $this_month->toDateTimeString(),
            "remarks" => ""
        ]);
        $twelfth_entry_id = $financial_entry_model->getInsertID();
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $twelfth_entry_id,
            "modifier_atom_id" => $collect_service_income_modifier_debit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1800"
        ]));
        $financial_entry_atom_model->insert(new FinancialEntryAtom([
            "financial_entry_id" => $twelfth_entry_id,
            "modifier_atom_id" => $collect_service_income_modifier_credit_atom_id,
            "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
            "numerical_value" => "1800"
        ]));
    }
}
