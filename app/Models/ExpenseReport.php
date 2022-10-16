<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Expense Report as represented in Workday.
 *
 * @property int $id
 * @property int $workday_instance_id
 * @property string $workday_expense_report_id
 * @property string $memo
 * @property \Illuminate\Support\Carbon $created_date
 * @property \Illuminate\Support\Carbon $approval_date
 * @property int $created_by_worker_id
 * @property int $fiscal_year_id
 * @property string|null $status
 * @property int $external_committee_member_id
 * @property float $amount
 * @property int|null $expense_payment_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\ExpensePayment|null $expensePayment
 * @property-read \App\Models\FiscalYear $fiscalYear
 * @property-read string $workday_url
 * @property-read \App\Models\ExternalCommitteeMember|null $payTo
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\DocuSignEnvelope> $envelopes
 * @property-read int|null $envelopes_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\ExpenseReportLine> $lines
 * @property-read int|null $lines_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport query()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereApprovalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereCreatedByWorkerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereCreatedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereExpensePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereExternalCommitteeMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereFiscalYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereMemo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereWorkdayExpenseReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpenseReport whereWorkdayInstanceId($value)
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class ExpenseReport extends Model
{
    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'float',
        'created_date' => 'date',
        'approval_date' => 'date',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workday_instance_id',
        'workday_expense_report_id',
        'fiscal_year_id',
        'external_committee_member_id',
        'created_by_worker_id',
        'memo',
        'created_date',
        'approval_date',
        'status',
        'amount',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'workday_instance_id';
    }

    /**
     * Get the expense payment for this expense report, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExpensePayment, self>
     */
    public function expensePayment(): BelongsTo
    {
        return $this->belongsTo(ExpensePayment::class, 'expense_payment_id', 'workday_instance_id');
    }

    /**
     * Get the external committee member for this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExternalCommitteeMember, self>
     */
    public function payTo(): BelongsTo
    {
        return $this->belongsTo(ExternalCommitteeMember::class, 'external_committee_member_id', 'workday_instance_id');
    }

    /**
     * Get the user that created this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_worker_id', 'workday_instance_id');
    }

    /**
     * Get the fiscal year for this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FiscalYear, self>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the lines for this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExpenseReportLine>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ExpenseReportLine::class, 'expense_report_id', 'workday_instance_id');
    }

    /**
     * Get the envelopes for this expense report.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\DocuSignEnvelope>
     */
    public function envelopes(): HasMany
    {
        return $this->hasMany(DocuSignEnvelope::class);
    }

    /**
     * Get the workday_url attribute to show this ECM in the Workday UI.
     *
     * @return string
     */
    public function getWorkdayUrlAttribute(): string
    {
        return 'https://wd5.myworkday.com/gatech/d/inst/1$1356/1356$'.$this->workday_instance_id.'.htmld';
    }
}
