<?php

namespace App\Libraries;

use CodeIgniter\I18n\Time;

class Resource
{
    public static function key(array $resources, callable $key_selector): array
    {
        $keyed_resource = array_reduce(
            $resources,
            function ($keyed_collection, $resource) use ($key_selector) {
                $keyed_collection[$key_selector($resource)] = $resource;

                return $keyed_collection;
            },
            []
        );

        return $keyed_resource;
    }

    public static function group(array $resources, callable $key_selector): array
    {
        $grouped_resource = array_reduce(
            $resources,
            function ($groups, $resource) use ($key_selector) {
                $key = $key_selector($resource);

                if (!isset($groups[$key])) {
                    $groups[$key] = [];
                }

                array_push($groups[$key], $resource);

                return $groups;
            },
            []
        );

        return $grouped_resource;
    }

    public static function duration(Time $started_time, Time $finished_time): int
    {
        $difference = $started_time
            ->setHour(0)->setMinute(0)->setSecond(0)
            ->difference($finished_time->setHour(0)->setMinute(0)->setSecond(0));
        $day_difference = $difference->getDays();
        $duration = $day_difference + 1;
        return $duration;
    }

    public static function retainExistingElements(array $resources): array
    {
        return array_values(array_filter($resources, fn ($resource) => $resource !== null));
    }
}
