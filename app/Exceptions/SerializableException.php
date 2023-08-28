<?php

namespace App\Exceptions;

use App\Contracts\APIExecption;

trait SerializableException
{
    public function serialize() {
        return [
            "errors" => [
                [
                    "message" => $this->getMessage()
                ]
            ]
        ];
    }
}
