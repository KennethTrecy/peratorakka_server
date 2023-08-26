<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;

class FrozenPeriodController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "frozen_period";
    }

    protected static function getCollectiveName(): string {
        return "frozen_periods";
    }

    protected static function getModelName(): string {
        return FrozenPeriodModel::class;
    }

    protected static function makeCreateValidation(): Validation {
        return static::makeValidation();
    }

    protected static function makeUpdateValidation(int $id): Validation {
        return static::makeValidation();
    }

    protected static function enrichResponseDocument(array $initial_document): array {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);

        if (!$is_single_main_document) {
            return $enriched_document;
        }

        $summary_calculations = model(SummaryCalculationModel::class)
            ->where("id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["summary_calculations"] = $summary_calculations;

        $linked_accounts = [];
        foreach ($summary_calculations as $document) {
            $account_id = $document->account_id;
            array_push($linked_accounts, $account_id);
        }

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }
        $enriched_document["accounts"] = $accounts;

        $linked_currencies = [];
        foreach ($accounts as $document) {
            $currency_id = $document->currency_id;
            array_push($linked_currencies, $currency_id);
        }

        $currencies = [];
        if (count($linked_currencies) > 0) {
            $currencies = model(CurrencyModel::class)
                ->whereIn("id", array_unique($linked_currencies))
                ->findAll();
        }
        $enriched_document["currencies"] = $currencies;

        return $enriched_document;
    }

    private static function makeValidation(): Validation {
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
            "must_be_on_or_before_current_time"
        ]);

        return $validation;
    }
}
