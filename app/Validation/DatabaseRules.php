<?php

namespace App\Validation;

use CodeIgniter\Validation\InvalidArgumentException;

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
            throw new InvalidArgumentException(
                'An owned resource model and search mode is required'
                .' in "ensure_ownership" to check ownership for field.'
            );
        }

        $model = model($parameters[0]);
        $id = $value;
        $current_user = auth()->user();
        $search_mode = $parameters[1];

        if (!$model->isOwnedBy($current_user, $search_mode, intval($id))) {
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
            throw new InvalidArgumentException(
                'A resource model, column to check, and acceptable list of column values is in'
                .' "has_column_value_in_list" to check if the selected option in field is unique.'
            );
        }

        $model = model($parameters[0]);
        $id = $value;
        $column = $parameters[1];
        $allowed_values = array_slice($parameters, 2);
        $entity = $model->find($id);

        if (!in_array($entity->$column, $allowed_values)) {
            return false;
        }

        return true;
    }

    public function permit_empty_if_column_value_matches(
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
            throw new InvalidArgumentException(
                'A resource model, column to check, and acceptable list of column values is in'
                .' "has_column_value_in_list" to check if the selected option in field is unique.'
            );
        }

        $model = model($parameters[0]);
        $id = $value;
        $column = $parameters[1];
        $allowed_values = array_slice($parameters, 2);
        $entity = $model->find($id);

        if (!in_array($entity->$column, $allowed_values)) {
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
            throw new InvalidArgumentException(
                'Number of parameters is fewer than required number'
                .' in "is_unique_compositely" to check if the value in field is unique.'
            );
        }

        $combined_parameters = explode("|", $parameters[0]);

        if (count($combined_parameters) < 2) {
            throw new InvalidArgumentException(
                'Number of combined parameters is fewer than required number'
                .' in "is_unique_compositely" to check if the value in field is unique.'
            );
        }

        $essential_parameters = explode(":", $combined_parameters[0]);
        if (
            count($essential_parameters) < 2
            || !(model($essential_parameters[0]) instanceof BaseResourceModel)
            || !in_array($essential_parameters[1], model($essential_parameters[0])->allowedFields)
        ) {
            throw new InvalidArgumentException(
                'A model name and column name of the existing value is required'
                .' in "is_unique_compositely" to check if the value in field is unique.'
            );
        }

        $extra_parameters = [];
        $parameter_count = count($combined_parameters);
        for ($i = 1; $i < $parameter_count; $i++) {
            $composite_column_data = $combined_parameters[$i];
            $position_of_equals = strpos($composite_column_data, "=");
            $position_of_pointer = strpos($composite_column_data, "->");

            $position_of_equals = $position_of_equals === false ? -1 : $position_of_equals;
            $position_of_pointer = $position_of_pointer === false ? -1 : $position_of_pointer;

            if (
                (
                    $position_of_pointer === -1
                    && $position_of_equals >= 0
                ) || (
                    $position_of_equals >= 0
                    && $position_of_pointer >= 0
                    && $position_of_equals < $position_of_pointer
                )
            ) {
                $extra_parameter = explode("=", $composite_column_data);

                array_push($extra_parameters, $extra_parameter);
            } else if (
                (
                    $position_of_equals === -1
                    && $position_of_pointer >= 0
                ) || (
                    $position_of_pointer >= 0
                    && $position_of_equals >= 0
                    && $position_of_pointer < $position_of_equals
                )
            ) {
                $extra_parameter = explode("->", $composite_column_data);
                $extra_parameter[1] = dot_array_search($extra_parameter[1], $data);

                array_push($extra_parameters, $extra_parameter);
            } else {
                throw new InvalidArgumentException(
                    'A valid composite data is required'
                    .' in "is_unique_compositely" to check if the value in field is unique.'
                );
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
                ->where($essential_parameters[1], $value)
        );
        $found_model = $query_builder->withDeleted()->first();

        if ($found_model !== null) {
            if (count($parameters) === 1) {
                $error = null;
                return false;
            }

            $ignored_parameters = explode("=", $parameters[1]);
            if (count($ignored_parameters) < 2) {
                throw new InvalidArgumentException(
                    'Ignore column and ignore value is required'
                    .' in "is_unique_compositely" to check if the value in field is unique.'
                );
            }

            $column_name = $ignored_parameters[0];
            $column_value = $ignored_parameters[1];
            if (strval($found_model->$column_name) !== $column_value) {
                $error = null;
                return false;
            }
        }

        return true;
    }
}
