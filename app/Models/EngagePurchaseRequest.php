<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Models;

use App\Exceptions\CouldNotExtractEngagePurchaseRequestNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * An Engage purchase request.
 *
 * @property int $id
 * @property int $engage_id
 * @property int $engage_request_number
 * @property string $subject
 * @property string|null $description
 * @property bool $approved
 * @property string $current_step_name
 * @property float $submitted_amount
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property int|null $submitted_by_user_id
 * @property float|null $approved_amount
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $approved_by_user_id
 * @property int|null $payee_user_id
 * @property string|null $payee_first_name
 * @property string|null $payee_last_name
 * @property string|null $payee_address_line_one
 * @property string|null $payee_address_line_two
 * @property string|null $payee_city
 * @property string|null $payee_state
 * @property string|null $payee_zip_code
 * @property int|null $expense_report_id
 * @property int|null $quickbooks_invoice_id
 * @property int|null $quickbooks_invoice_document_number
 * @property int $fiscal_year_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\User|null $approvedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\ExpenseReport|null $expenseReport
 * @property-read \App\Models\FiscalYear $fiscalYear
 * @property-read string|null $engage_url
 * @property-read string|null $quickbooks_invoice_url
 * @property-read \App\Models\User|null $payToUser
 * @property-read \App\Models\User|null $submittedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereApprovedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereApprovedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereApprovedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereCurrentStepName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereEngageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereEngageRequestNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereExpenseReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereFiscalYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeAddressLineOne($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeAddressLineTwo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest wherePayeeZipCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereQuickbooksInvoiceDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereQuickbooksInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereSubmittedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereSubmittedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|EngagePurchaseRequest withoutTrashed()
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class EngagePurchaseRequest extends Model
{
    use Searchable;
    use SoftDeletes;

    private const PURCHASE_REQUEST_NUMBER_REGEX = '/(?:Purchase Request|Request No):\s+(?P<requestNumber>\d{7})/';

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
        'approved' => 'boolean',
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

    public static function getPurchaseRequestNumberFromText(string $pdf_text): int
    {
        $matches = [];

        if (preg_match_all(self::PURCHASE_REQUEST_NUMBER_REGEX, $pdf_text, $matches) === false) {
            throw new CouldNotExtractEngagePurchaseRequestNumber('preg_match_all returned false');
        }

        return intval(collect($matches['requestNumber'])->uniqueStrict()->sole());
    }
}
