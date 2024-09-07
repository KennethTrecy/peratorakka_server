<?php

namespace App\Libraries;

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
}
