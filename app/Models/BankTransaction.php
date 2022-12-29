<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/**
 * A bank transaction.
 *
 * @property int $id
 * @property string $bank
 * @property string|null $bank_transaction_id
 * @property string $bank_description
 * @property string|null $note
 * @property string|null $transaction_reference
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $transaction_created_at
 * @property \Illuminate\Support\Carbon|null $transaction_posted_at
 * @property float $net_amount
 * @property int|null $check_number
 * @property string|null $kind
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ExpensePayment|null $expensePayment
 *
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBankDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBankTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCheckNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereNetAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereTransactionCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereTransactionPostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereUpdatedAt($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class BankTransaction extends Model
{
    use Searchable;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'net_amount' => 'float',
        'transaction_created_at' => 'datetime',
        'transaction_posted_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bank',
        'bank_transaction_id',
        'bank_description',
        'transaction_reference',
        'transaction_created_at',
        'transaction_posted_at',
        'net_amount',
        'check_number',
        'status',
        'note',
        'kind',
    ];

    /**
     * List of valid banks and display names for them.
     *
     * @var array<string,string>
     *
     * @phan-read-only
     */
    public static array $banks = [
        'mercury' => 'Mercury',
        'wells_fargo' => 'Wells Fargo',
    ];

    /**
     * Get the expense payment for this expense report, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<\App\Models\ExpensePayment>
     */
    public function expensePayment(): HasOne
    {
        return $this->hasOne(ExpensePayment::class);
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['net_amount'] = strval($this->net_amount);

        return $array;
    }
}
