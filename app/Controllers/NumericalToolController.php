<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Models\CurrencyModel;
use App\Models\NumericalToolModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

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

        $user_id = $owner->id;

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ])
            ])."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $user_id = $owner->id;

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(array $initial_document): array
    {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        $linked_currencies = array_filter(array_map(function ($main_document) {
            $output_format = explode(
                "#",
                $main_document->configuration->sources[0]->outputFormatCode()
            );
            if ($output_format[0] === CURRENCY_FORMULA_OUTPUT_FORMAT) {
                return intval($output_format[1]);
            }

            return null;
        }, $main_documents), function ($currency_id) {
            return $currency_id !== null;
        });
        if (count($linked_currencies) > 0) {
            $currencies = model(CurrencyModel::class)
                ->selectUsingMultipleIDs($linked_currencies);
            $enriched_document["currencies"] = $currencies;
        }

        return $enriched_document;
    }

    protected static function prepareRequestData(array $raw_request_data): array
    {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    public function calculate(int $id)
    {
        helper("auth");

        $current_user = auth()->user();
        $model = static::getModel();
        $data = $model->find($id);

        $is_success = !is_null($data);

        if ($is_success) {
            $constellations = NumericalToolModel::showConstellations($data);
            $response_document = [
                "@meta" => [
                    "constellations" => $constellations
                ],
                static::getIndividualName() => $data,
            ];
            $response_document = static::enrichResponseDocument($response_document);
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
