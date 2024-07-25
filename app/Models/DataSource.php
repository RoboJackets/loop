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
     * The primary key associated with the table.
     *
     * @var string
     *
     * @phan-read-only
     */
    protected $primaryKey = 'name';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     *
     * @phan-read-only
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     *
     * @phan-read-only
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     *
     * @phan-read-only
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $fillable = [
        'name',
        'synced_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
