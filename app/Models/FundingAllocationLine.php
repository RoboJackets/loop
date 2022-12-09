<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * A specific line item of a funding allocation, such as in an SGA budget or bill.
 *
 * @property int $id
 * @property int $funding_allocation_id
 * @property int $line_number
 * @property string $description
 * @property float $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\DocuSignEnvelope> $envelopes
 * @property-read int|null $envelopes_count
 * @property-read \App\Models\FundingAllocation $fundingAllocation
 * @property-read string $name
 *
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine newQuery()
 * @method static \Illuminate\Database\Query\Builder|FundingAllocationLine onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine query()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereFundingAllocationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereLineNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocationLine whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|FundingAllocationLine withTrashed()
 * @method static \Illuminate\Database\Query\Builder|FundingAllocationLine withoutTrashed()
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
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
        $array['amount'] = strval($this->amount);

        return $array;
    }

    /**
     * Get the envelopes for this funding allocation line.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\DocuSignEnvelope>
     */
    public function envelopes(): BelongsToMany
    {
        return $this->belongsToMany(DocuSignEnvelope::class, 'docusign_funding_sources')
            ->withPivot(['amount'])
            ->withTimestamps()
            ->using(DocuSignFundingSource::class);
    }
}
