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

    public function is_unique_compositely(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        $parameters = explode(",", $parameters);

        if (count($parameters) < 2) {
            $error = 'Number of parameters is fewer than required number'
                .' in "{0}" to check if the value in {field} is unique.';
            return false;
        }

        $essential_parameters = explode(":", $parameters[0]);
        if (
            count($essential_parameters) < 2
            || !(model($essential_parameters[0]) instanceof BaseResourceModel)
            || !in_array($essential_parameters[1], model($essential_parameters[0])->allowedFields)
        ) {
            $error = 'A model name and column name of the existing value is required'
                .' in "{0}" to check if the value in {field} is unique.';
            return false;
        }

        return true;
    }
}
