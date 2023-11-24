<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

/**
 * An email request.
 *
 * @property int $id
 * @property string|null $vendor_name
 * @property float|null $vendor_document_amount
 * @property string|null $vendor_document_reference
 * @property \Illuminate\Support\Carbon|null $vendor_document_date
 * @property string $vendor_document_filename
 * @property string|null $sensible_extraction_uuid
 * @property array|null $sensible_output
 * @property int|null $expense_report_id
 * @property int|null $quickbooks_invoice_id
 * @property int|null $quickbooks_invoice_document_number
 * @property int|null $fiscal_year_id
 * @property \Illuminate\Support\Carbon|null $email_sent_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\ExpenseReport|null $expenseReport
 * @property-read \App\Models\FiscalYear|null $fiscalYear
 * @property-read string|null $quickbooks_invoice_url
 * @property-read string|null $sensible_extraction_url
 * @property-read string|null $vendor_document_thumbnail_url
 *
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereEmailSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereExpenseReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereFiscalYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereQuickbooksInvoiceDocumentNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereQuickbooksInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereSensibleExtractionUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereSensibleOutput($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereVendorDocumentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereVendorDocumentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereVendorDocumentFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereVendorDocumentReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest whereVendorName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|EmailRequest withoutTrashed()
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class EmailRequest extends Model
{
    use Searchable;
    use SoftDeletes;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'vendor_document_amount' => 'float',
        'vendor_document_date' => 'date',
        'email_sent_at' => 'datetime',
        'deleted_at' => 'datetime',
        'sensible_output' => 'array',
    ];

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
     * Get the expense report for this request, if available.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExpenseReport, self>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
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
     * Get the sensible_extraction_url attribute to show this request in the Sensible UI.
     */
    public function getSensibleExtractionUrlAttribute(): ?string
    {
        return $this->sensible_extraction_uuid === null
            ? null
            : 'https://app.sensible.so/extraction/?e='.$this->sensible_extraction_uuid;
    }

    public function getQuickbooksInvoiceUrlAttribute(): ?string
    {
        return $this->quickbooks_invoice_id === null
            ? null
            : 'https://app.qbo.intuit.com/app/invoice?txnId='.$this->quickbooks_invoice_id;
    }

    public function getVendorDocumentThumbnailUrlAttribute(): ?string
    {
        if ($this->vendor_document_filename === null) {
            return null;
        }

        $full_file_path = Storage::disk('local')->path($this->vendor_document_filename);

        if (! file_exists($full_file_path)) {
            return null;
        }

        $thumbnail_relative_path = '/thumbnail/'.hash_file('sha512', $full_file_path).'.png';

        if (! Storage::disk('public')->exists($thumbnail_relative_path)) {
            return null;
        }

        return '/storage'.$thumbnail_relative_path;
    }
}
