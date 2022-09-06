<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class FundingAllocation extends Model
{
    use SoftDeletes;
    use Searchable;

    /**
     * List of valid types and display names for them.
     *
     * @var array<string,string>
     *
     * @phan-read-only
     */
    public static array $types = [
        'sga_budget' => 'SGA Budget',
        'sga_bill' => 'SGA Bill',
        'agency' => 'Agency',
        'foundation' => 'Foundation',
    ];

    /**
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'sga_bill_number',
        'type',
        'fiscal_year_ending_year',
    ];

    /**
     * The attributes that can be used for filtering in Meilisearch.
     *
     * @var array<string>
     */
    public array $filterable_attributes = [
        'fiscal_year_id',
    ];

    /**
     * Get the fiscal year for this funding allocation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FiscalYear, \App\Models\FundingAllocation>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the lines for this funding allocation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FundingAllocationLine>
     */
    public function fundingAllocationLines(): HasMany
    {
        return $this->hasMany(FundingAllocationLine::class);
    }

    /**
     * Get the display name for this funding allocation.
     */
    public function getNameAttribute(): string
    {
        return self::$types[$this->type].' '.
            ($this->type === 'sga_bill' ? $this->sga_bill_number : 'FY'.$this->fiscalYear->ending_year);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['fiscal_year_ending_year'] = $this->fiscalYear->ending_year;

        return $array;
    }
}
