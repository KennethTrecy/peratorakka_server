<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Currency extends Entity
{
    protected $datamap = [];

    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    protected $casts = [
        "id" => "integer",
        "user_id" => "integer",
        "code" => "string",
        "name" => "string"
    ];

    public function toArray(
        bool $onlyChanged = false,
        bool $cast = true,
        bool $recursive = false
    ): array {
        $array_entity = parent::toArray($onlyChanged, $cast, $recursive);

        foreach ($this->dates as $date_property) {
            if (isset($array_entity[$date_property])) {
                $array_entity[$date_property] = $array_entity[$date_property]
                    ->setTimezone("UTC")
                    ->toDateTimeString();
            }
        }

        return $array_entity;
    }
}
