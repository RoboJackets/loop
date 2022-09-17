<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * A funding allocation, such as an SGA budget or bill.
 *
 * @property int $id
 * @property int $fiscal_year_id
 * @property string|null $sga_bill_number
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\FiscalYear $fiscalYear
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\FundingAllocationLine> $fundingAllocationLines
 * @property-read int|null $funding_allocation_lines_count
 * @property-read string $name
 * @property-read string|null $type_display_name
 *
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation newQuery()
 * @method static \Illuminate\Database\Query\Builder|FundingAllocation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereFiscalYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereSgaBillNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FundingAllocation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|FundingAllocation withTrashed()
 * @method static \Illuminate\Database\Query\Builder|FundingAllocation withoutTrashed()
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
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
     * The attributes that Nova might think can be used for filtering, but actually can't.
     *
     * @var array<string>
     */
    public array $do_not_filter_on = [
        'funding_allocation_line_id',
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
     * Get the type display name for this funding allocation.
     */
    public function getTypeDisplayNameAttribute(): ?string
    {
        return $this->type === null ? null : self::$types[$this->type];
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
