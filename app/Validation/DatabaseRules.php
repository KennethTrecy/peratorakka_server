<?php

namespace App\Validation;

use App\Contracts\OwnedResource;

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
}
