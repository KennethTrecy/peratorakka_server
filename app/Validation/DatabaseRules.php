<?php

namespace App\Validation;

use App\Contracts\OwnedResource;
use App\Models\BaseResourceModel;

class DatabaseRules {
    public function ensure_ownership(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 2
            || !(model($parameters[0]) instanceof OwnedResource)
            || !in_array($parameters[1], [
                SEARCH_NORMALLY,
                SEARCH_WITH_DELETED,
                SEARCH_ONLY_DELETED
            ])
        ) {
            $error = 'An owned resource model and search mode is required'
                .' in "{0}" to check ownership for {field}.';
            return false;
        }

        $model = model($parameters[0]);
        $id = $value;
        $current_user = auth()->user();
        $search_mode = $parameters[1];

        if (!$model->isOwnedBy($current_user, $search_mode, intval($id))) {
            $error = "{field} must be owned by the current user and present.";
            return false;
        }

        return true;
    }

    public function has_column_value_in_list(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);

        if (
            count($parameters) < 2
            || !(model($parameters[0]) instanceof BaseResourceModel)
            || !in_array($parameters[1], model($parameters[0])->allowedFields)
        ) {
            $error = 'A resource model, column to check, and acceptable list of column values is'
                .' required in "{0}" to check if the selected option in {field} is allowed.';
            return false;
        }

        $model = model($parameters[0]);
        $id = $value;
        $column = $parameters[1];
        $allowed_values = array_slice($parameters, 2);
        $entity = $model->find($id);

        if (!in_array($entity->$column, $allowed_values)) {
            $error = "{field} does not match the acceptable values.";
            return false;
        }

        return true;
    }

    /**
     * Validator syntax: `is_unique_compositely[
     *      model:column_name
     *      |other_column->body_pointer
     *      |other_column=raw_value,
     *      optional_field=optional_value
     * ]`
     */
    public function is_unique_compositely(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        helper("array");

        $parameters = explode(",", $parameters);

        if (count($parameters) < 1) {
            $error = 'Number of parameters is fewer than required number'
                .' in "{0}" to check if the value in {field} is unique.';
            return false;
        }

        $combined_parameters = explode("|", $parameters[0]);

        if (count($combined_parameters) < 2) {
            $error = 'Number of combined parameters is fewer than required number'
                .' in "{0}" to check if the value in {field} is unique.';
            return false;
        }

        $essential_parameters = explode(":", $combined_parameters[0]);
        if (
            count($essential_parameters) < 2
            || !(model($essential_parameters[0]) instanceof BaseResourceModel)
            || !in_array($essential_parameters[1], model($essential_parameters[0])->allowedFields)
        ) {
            $error = 'A model name and column name of the existing value is required'
                .' in "{0}" to check if the value in {field} is unique.';
            return false;
        }

        $extra_parameters = [];
        $parameter_count = count($combined_parameters);
        for ($i = 1; $i < $parameter_count; $i++) {
            $composite_column_data = $combined_parameters[$i];
            $position_of_equals = strpos($composite_column_data, "=");
            $position_of_pointer = strpos($composite_column_data, "->");

            if (
                (
                    $position_of_pointer === -1
                    && $position_of_equals >= 0
                ) || (
                    $position_of_equals < $position_of_pointer
                )
            ) {
                $extra_parameter = explode("=", $composite_column_data);
                array_push($extra_parameters, $extra_parameter);
            } else if (
                (
                    $position_of_equals === -1
                    && $position_of_pointer >= 0
                ) || (
                    $position_of_pointer < $position_of_equals
                )
            ) {
                $extra_parameter = explode("->", $composite_column_data);
                $extra_parameter[1] = dot_array_search($extra_parameter[1], $data);

                array_push($extra_parameters, $extra_parameter);
            } else {
                $error = 'A valid composite data is required'
                    .' in "{0}" to check if the value in {field} is unique.';
                return false;
            }
        }

        $query_builder = array_reduce(
            $extra_parameters,
            function($builder, $extra_parameter) {
                return $builder->where(
                    $extra_parameter[0],
                    $extra_parameter[1]
                );
            },
            model($essential_parameters[0], false)
                ->where($essential_parameters[0], $value)
        );
        $found_model = $query_builder->withDeleted()->first();

        if ($found_model !== null) {
            if (count($parameters) === 1) {
                $error = '{field} must be a unique value in the database.';
                return false;
            }

            $ignored_parameters = explode("=", $parameters[2]);
            if (count($ignored_parameters) < 2) {
                $error = 'Ignore column and ignore value is required'
                    .' in "{0}" to check if the value in {field} is unique.';
                return false;
            }

            $column_name = $ignored_parameters[0];
            $column_value = $ignored_parameters[1];
            if ($found_model->$column_name !== $column_value) {
                $error = '{field} must be a unique value in the database.';
                return false;
            }
        }

        return true;
    }
}
