<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Models\CurrencyModel;
use App\Models\NumericalToolModel;
use App\Exceptions\UnprocessableRequest;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;
use CodeIgniter\I18n\Time;

class NumericalToolController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "numerical_tool";
    }

    protected static function getCollectiveName(): string
    {
        return "numerical_tools";
    }

    protected static function getModelName(): string
    {
        return NumericalToolModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.currency_id", "currency", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                CurrencyModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "currency_id->$individual_name.currency_id"
                ])
            ])."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "currency_id->$individual_name.currency_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(
        array $initial_document,
        array $relationships
    ): array {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        $must_include_all = in_array("*", $relationships);
        $must_include_precision_format = $must_include_all
            || in_array("precision_formats", $relationships);
        $must_include_currency = $must_include_all || in_array("currencies", $relationships);
        if ($must_include_precision_format || $must_include_currency) {
            [
                $currencies,
                $precision_formats
            ] = NumericalToolModel::selectAncestorsWithResolvedResources($main_documents);
            $enriched_document["precision_formats"] = $precision_formats;
            $enriched_document["currencies"] = $currencies;
        }

        return $enriched_document;
    }

    public function calculate(int $id)
    {
        helper("auth");

        $current_date = Time::today();

        $request = $this->request;
        $reference_date = $request->getVar("reference_date") ?? $current_date->toDateString();
        $relationship = $this->identifyRequiredRelationship();

        $reference_date = $time = Time::createFromFormat(
            DATE_STRING_FORMAT,
            $reference_date,
            DATE_TIME_ZONE
        );

        if ($reference_date === false) {
            throw new UnprocessableRequest("Unable to understand the reference date.");
        }

        $current_user = auth()->user();
        $model = static::getModel();
        $data = $model->find($id);

        $is_success = !is_null($data);

        if ($is_success) {
            [
                $time_tags,
                $constellations
            ] = NumericalToolModel::showConstellations($reference_date, $data);
            $response_document = [
                "@meta" => [
                    "time_tags" => $time_tags,
                    "constellations" => $constellations
                ],
                static::getIndividualName() => $data
            ];
            $response_document = static::enrichResponseDocument($response_document, $relationship);
            ksort($response_document);

            return $this->response->setJSON($response_document);
        } else {
            throw new MissingResource();
        }
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "numerical tool info", [
            "required"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_NUMERICAL_TOOL_KINDS)."]"
        ]);
        $validation->setRule("$individual_name.recurrence", "recurrence", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_NUMERICAL_TOOL_RECURRENCE_PERIODS)."]"
        ]);
        $validation->setRule("$individual_name.recency", "recency", [
            "required",
            "is_integer"
        ]);
        $validation->setRule("$individual_name.order", "order", [
            "required",
            "is_natural"
        ]);
        $validation->setRule("$individual_name.notes", "notes", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);
        $validation->setRule("$individual_name.configuration", "configuration", [
            "required",
            "string",
            "valid_json",
            "valid_numerical_tool_configuration"
        ]);

        return $validation;
    }
}
