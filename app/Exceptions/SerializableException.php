<?php

namespace App\Exceptions;

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
