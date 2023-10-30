<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class EngagePurchaseRequest extends Model
{
    use Searchable;
    use SoftDeletes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'submitted_amount' => 'float',
        'approved_amount' => 'float',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'approved',
        'approved_amount',
        'approved_at',
        'approved_by_user_id',
        'current_step_name',
        'deleted_at',
        'description',
        'engage_id',
        'engage_request_number',
        'fiscal_year_id',
        'payee_address_line_one',
        'payee_address_line_two',
        'payee_city',
        'payee_first_name',
        'payee_last_name',
        'payee_state',
        'payee_zip_code',
        'subject',
        'submitted_amount',
        'submitted_at',
        'submitted_by_user_id',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'engage_id';
    }

    /**
     * Get the fiscal year for this request.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FiscalYear, self>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the payee for this request, if it is a known user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function payToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id');
    }

    /**
     * Get the user that created this request.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * Get the user that created this request.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Get the attachments associated with the request.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the expense report for this request, if available.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExpenseReport, self>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
    }

    public function getQuickbooksInvoiceUrlAttribute(): ?string
    {
        return $this->quickbooks_invoice_id === null
            ? null
            : 'https://app.qbo.intuit.com/app/invoice?txnId='.$this->quickbooks_invoice_id;
    }

    public function getEngageUrlAttribute(): ?string
    {
        return $this->engage_id === null
            ? null
            : 'https://gatech.campuslabs.com/engage/finance/robojackets/requests/purchase/'.$this->engage_id;
    }
}
