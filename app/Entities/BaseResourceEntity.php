<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

abstract class BaseResourceEntity extends Entity
{
    protected $dates = [
        "created_at",
        "updated_at",
        "deleted_at"
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
