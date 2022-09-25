<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * An External Committee Member as represented in Workday.
 *
 * @property int $id
 * @property int $workday_instance_id
 * @property string $workday_external_committee_member_id
 * @property string $name
 * @property bool $active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $workday_url
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember query()
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereWorkdayExternalCommitteeMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExternalCommitteeMember whereWorkdayInstanceId($value)
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class ExternalCommitteeMember extends Model
{
    use Searchable;

    public const WORKDAY_NAME_REGEX = '/^(?P<name>^[a-zA-Z\s]+)\s+\(ECM\)(?P<inactive>\s+-\s+Inactive)?$/';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workday_instance_id',
        'workday_external_committee_member_id',
        'name',
        'active',
    ];

    /**
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'name',
        'workday_external_committee_member_id',
    ];

    /**
     * Get the workday_url attribute to show this ECM in the Workday UI.
     *
     * @return string
     */
    public function getWorkdayUrlAttribute(): string
    {
        return 'https://wd5.myworkday.com/gatech/d/inst/1$15341/15341$'.$this->workday_instance_id.'.htmld';
    }
}
