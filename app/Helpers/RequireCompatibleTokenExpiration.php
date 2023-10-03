<?php

namespace App\Helpers;

use CodeIgniter\HTTP\RequestInterface;

trait RequireCompatibleTokenExpiration {
    public function hasCompatibleTokenExpirationType(RequestInterface $request): bool {
        $current_body = $this->request->getJSON(true);

        return
            isset($current_body["@meta"])
            && isset($current_body["@meta"]["expiration_type"])
            && count(
                array_diff(
                    SUPPORTED_TOKEN_EXPIRATION_TYPES,
                    [ $current_body["@meta"]["expiration_type"] ]
                )
            ) < count(SUPPORTED_TOKEN_EXPIRATION_TYPES);
    }
}
