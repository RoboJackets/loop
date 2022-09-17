<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fiscal year.
 *
 * @property int $id
 * @property int $ending_year
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\DocuSignEnvelope> $envelopes
 * @property-read int|null $envelopes_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\FundingAllocation> $fundingAllocations
 * @property-read int|null $funding_allocations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear query()
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereEndingYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|FiscalYear whereUpdatedAt($value)
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class FiscalYear extends Model
{
    /**
     * Get the funding allocations for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\FundingAllocation>
     */
    public function fundingAllocations(): HasMany
    {
        return $this->hasMany(FundingAllocation::class);
    }

    /**
     * Get the DocuSign envelopes for this fiscal year.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\DocuSignEnvelope>
     */
    public function envelopes(): HasMany
    {
        return $this->hasMany(DocuSignEnvelope::class);
    }

    public static function fromDate(Carbon $date): self
    {
        return self::where('ending_year', self::intFromDate($date))->sole();
    }

    public static function intFromDate(Carbon $date): int
    {
        return $date->year + ($date->month < 7 ? 0 : 1);
    }
}
