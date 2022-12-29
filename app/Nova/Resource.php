<?php

declare(strict_types=1);

namespace App\Nova;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Laravel\Scout\Builder;

/**
 * Base class for Nova resources.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @mixin TModel  @phan-suppress-current-line PhanInvalidMixin
 *
 * @method string getKey()
 */
abstract class Resource extends NovaResource
{
    /**
     * Build a Scout search query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Laravel\Scout\Builder  $query
     * @return \Laravel\Scout\Builder
     */
    public static function scoutQuery(NovaRequest $request, $query): Builder
    {
        if ($request->viaResource !== null) {
            $filter_on_attribute = Str::replace('-', '_', Str::singular($request->viaResource)).'_id';

            if (! property_exists($query->model, 'filterable_attributes')) {
                throw new \Exception(
                    'Attempted to query Scout model '.get_class($query->model).' with filter '.$filter_on_attribute
                    .', but model does not have $filterable_attributes'
                );
            }

            if (! in_array($filter_on_attribute, $query->model->filterable_attributes, true)) {
                if (property_exists($query->model, 'do_not_filter_on')) {
                    if (in_array($filter_on_attribute, $query->model->do_not_filter_on, true)) {
                        return $query;
                    }

                    throw new \Exception(
                        'Attempted to query Scout model '.get_class($query->model).' with filter '.$filter_on_attribute
                        .', but filter not in $filterable_attributes nor $do_not_filter_on'
                    );
                }

                throw new \Exception(
                    'Attempted to query Scout model '.get_class($query->model).' with filter '.$filter_on_attribute
                    .', but filter not in $filterable_attributes and model does not have $do_not_filter_on'
                );
            }

            return $query->where($filter_on_attribute, $request->viaResourceId);
        }

        return $query;
    }

    public static function queryParamFromReferrer(Request $request, string $param_name): ?string
    {
        $header_value = $request->header('referer');

        if ($header_value === null) {
            return null;
        }

        $query_string = parse_url($header_value, PHP_URL_QUERY);

        if ($query_string === false || $query_string === null) {
            return null;
        }

        $query_params = [];

        parse_str($query_string, $query_params);

        if (! array_key_exists($param_name, $query_params)) {
            return null;
        }

        return $query_params[$param_name];
    }
}
