<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public static function fromDate(Carbon $date): self
    {
        return self::where('ending_year', $date->year + ($date->month < 7 ? 0 : 1))->sole();
    }
}
