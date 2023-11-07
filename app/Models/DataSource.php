<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A data source used for reconciliation.
 *
 * @property string $name
 * @property \Illuminate\Support\Carbon $synced_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|DataSource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataSource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataSource query()
 * @method static \Illuminate\Database\Eloquent\Builder|DataSource whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataSource whereSyncedAt($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class DataSource extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'synced_at',
    ];
}
