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
     * Get the sensible_extraction_url attribute to show this envelope in the Sensible UI.
     *
     * @return ?string
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
