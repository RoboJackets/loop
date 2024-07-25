<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\GetMorphClassStatic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

/**
 * An Expense Report Line as represented in Workday.
 *
 * @property int $id
 * @property int $workday_line_id
 * @property int $expense_report_id
 * @property float $amount
 * @property string|null $memo
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\ExpenseReport $expenseReport
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine query()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereExpenseReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReportLine whereWorkdayLineId($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class ExpenseReportLine extends Model
{
    use GetMorphClassStatic;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $fillable = [
        'workday_line_id',
        'expense_report_id',
        'amount',
        'memo',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $with = [
        'expenseReport',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    /**
     * Get the user that created this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExpenseReport, self>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class, 'expense_report_id', 'workday_instance_id');
    }

    /**
     * Get the attachments associated with the expense report line.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'workday_line_id';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['amount'] = strval($this->amount);

        return $array;
    }
}
