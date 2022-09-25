<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class ExternalCommitteeMember extends Model
{
    use Searchable;

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
