<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An Expense Payment as represented in Workday.
 *
 * @property int $id
 * @property int $workday_instance_id
 * @property string $status
 * @property bool $reconciled
 * @property int $external_committee_member_id
 * @property \Illuminate\Support\Carbon $payment_date
 * @property float $amount
 * @property int $transaction_reference
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\ExpenseReport> $expenseReports
 * @property-read int|null $expense_reports_count
 * @property-read \App\Models\ExternalCommitteeMember|null $payTo
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment query()
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereExternalCommitteeMemberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereReconciled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ExpensePayment whereWorkdayInstanceId($value)
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class ExpensePayment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workday_instance_id',
        'status',
        'reconciled',
        'external_committee_member_id',
        'payment_date',
        'amount',
        'transaction_reference',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'float',
        'reconciled' => 'boolean',
        'payment_date' => 'datetime',
    ];

    /**
     * Get the payee for this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExternalCommitteeMember, self>
     */
    public function payTo(): BelongsTo
    {
        return $this->belongsTo(ExternalCommitteeMember::class, 'external_committee_member_id', 'workday_instance_id');
    }

    /**
     * Get the expense reports associated with this expense payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExpenseReport>
     */
    public function expenseReports(): HasMany
    {
        return $this->hasMany(ExpenseReport::class, 'expense_payment_id', 'workday_instance_id');
    }
}
