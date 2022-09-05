<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class FundingAllocationLine extends Model
{
    use SoftDeletes;
    use Searchable;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'funding_allocation_id',
        'line_number',
        'description',
        'amount',
    ];

    /**
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'description',
        'line_number',
        'amount',
    ];

    /**
     * The attributes that can be used for filtering in Meilisearch.
     *
     * @var array<string>
     */
    public array $filterable_attributes = [
        'funding_allocation_id',
    ];

    /**
     * Get the fiscal year for this funding allocation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FundingAllocation, \App\Models\FundingAllocationLine>
     */
    public function fundingAllocation(): BelongsTo
    {
        return $this->belongsTo(FundingAllocation::class);
    }

    /**
     * Get the display name for this funding allocation.
     */
    public function getNameAttribute(): string
    {
        return $this->fundingAllocation->name.
            (
                str_starts_with($this->fundingAllocation->type, 'sga_') ? ' Line '.$this->line_number : ''
            );
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['funding_allocation_name'] = $this->fundingAllocation->name;

        return $array;
    }

    /**
     * Get the envelopes for this funding allocation line.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\DocuSignEnvelope>
     */
    public function envelopes(): BelongsToMany
    {
        return $this->belongsToMany(
            DocuSignEnvelope::class,
            'docusign_funding_sources',
            'funding_allocation_line_id',
            'docusign_envelope_id'
        )
            ->withPivot(['amount'])
            ->withTimestamps()
            ->using(DocuSignFundingSource::class);
    }
}
