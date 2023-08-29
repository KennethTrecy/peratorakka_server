<?php

namespace App\Exceptions;

trait SerializableException
{
    public function serialize(): array {
        return [
            "errors" => [
                [
                    "message" => $this->getMessage()
                ]
            ]
        ];
    }
}
