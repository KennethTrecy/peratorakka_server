<?php

namespace App\Helpers;

use CodeIgniter\HTTP\RequestInterface;

trait RequireCompatibleTokenExpiration
{
    public function hasCompatibleTokenExpirationType(RequestInterface $request): bool
    {
        $current_body = $request->getJSON(true);

        return
            isset($current_body["@meta"])
            && isset($current_body["@meta"]["expiration_types"])
            && count(
                array_diff(
                    SUPPORTED_TOKEN_EXPIRATION_TYPES,
                    [ ...$current_body["@meta"]["expiration_types"] ]
                )
            ) < count(SUPPORTED_TOKEN_EXPIRATION_TYPES);
    }
}
