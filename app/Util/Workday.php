<?php

declare(strict_types=1);

namespace App\Util;

class Workday
{
    /**
     * Inspired by https://stackoverflow.com/a/1019126.
     *
     * @psalm-pure
     */
    public static function searchForKeyValuePair(array $widgets, string $key, string $value): array
    {
        $results = [];

        if (array_key_exists($key, $widgets) && $widgets[$key] === $value) {
            $results[] = $widgets;
        } else {
            foreach ($widgets as $sub_array) {
                if (is_array($sub_array)) {
                    $results = array_merge($results, self::searchForKeyValuePair($sub_array, $key, $value));
                }
            }
        }

        return $results;
    }

    /**
     * Converts Workday date format to ISO 8601.
     *
     * @param  array<string,array<string,string>>  $widget
     *
     * @psalm-pure
     */
    public static function getDate(array $widget): string
    {
        return $widget['value']['Y'].'-'.$widget['value']['M'].'-'.$widget['value']['D'];
    }

    /**
     * Extracts the instance ID from a widget.
     *
     * @psalm-pure
     */
    public static function getInstanceId(array $widget): string
    {
        return explode('$', $widget['instances'][0]['instanceId'])[1];
    }

    public static function sole(array $widgets, string $property_name): array
    {
        return collect(self::searchForKeyValuePair($widgets, 'propertyName', $property_name))->sole();
    }
}
