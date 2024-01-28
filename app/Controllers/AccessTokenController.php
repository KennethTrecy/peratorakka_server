<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\TokenLoginModel;
use CodeIgniter\Validation\Validation;

use App\Models\AccessTokenModel;

class AccessTokenController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "access_token";
    }

    protected static function getCollectiveName(): string {
        return "access_tokens";
    }

    protected static function getModelName(): string {
        return AccessTokenModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation {
        return static::makeValidation();
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation {
        return static::makeValidation();
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");

        return $validation;
    }
}
