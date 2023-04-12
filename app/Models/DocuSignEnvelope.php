<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore

namespace App\Models;

use App\Exceptions\CouldNotExtractEnvelopeUuid;
use App\Traits\GetMorphClassStatic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Smalot\PdfParser\Parser;

/**
 * A DocuSign envelope.
 *
 * @property int $id
 * @property string $envelope_uuid
 * @property string|null $type
 * @property string|null $supplier_name
 * @property string|null $description
 * @property float|null $amount
 * @property int|null $pay_to_user_id
 * @property string $sofo_form_filename
 * @property string $summary_filename
 * @property string|null $sensible_extraction_uuid
 * @property array|null $sensible_output
 * @property int|null $fiscal_year_id
 * @property int|null $replaces_docusign_envelope_id
 * @property bool $lost
 * @property int|null $expense_report_id
 * @property bool $internal_cost_transfer
 * @property int|null $duplicate_of_docusign_envelope_id
 * @property bool $submission_error
 * @property int|null $quickbooks_invoice_id
 * @property int|null $quickbooks_invoice_document_number
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read \App\Models\FiscalYear|null $fiscalYear
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\FundingAllocationLine> $fundingSources
 * @property-read int|null $funding_sources_count
 * @property-read \App\Models\User|null $payToUser
 * @property-read DocuSignEnvelope|null $replacesEnvelope
 * @property-read \App\Models\ExpenseReport|null $expenseReport
 * @property-read string|null $sensible_extraction_url
 * @property-read string|null $quickbooks_invoice_url
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\DocuSignEnvelope> $replacedBy
 * @property-read int|null $replaced_by_count
 * @property-read DocuSignEnvelope|null $duplicateOf
 * @property-read \Illuminate\Database\Eloquent\Collection|array<DocuSignEnvelope> $duplicates
 * @property-read int|null $duplicates_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope newQuery()
 * @method static \Illuminate\Database\Query\Builder|DocuSignEnvelope onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope query()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereEnvelopeUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereExpenseReportId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereFiscalYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope wherePayToUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereReplacesDocusignEnvelopeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSensibleExtractionUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSensibleOutput($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSofoFormFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSummaryFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSupplierName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereDuplicateOfDocusignEnvelopeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereInternalCostTransfer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereSubmissionError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereQuickbooksInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereQuickbooksInvoiceDocumentNumber($value)
 * @method static \Illuminate\Database\Query\Builder|DocuSignEnvelope withTrashed()
 * @method static \Illuminate\Database\Query\Builder|DocuSignEnvelope withoutTrashed()
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class DocuSignEnvelope extends Model
{
    use SoftDeletes;
    use Searchable;
    use GetMorphClassStatic;

    private const ENVELOPE_ID_REGEX = '/Envelope Id: (?P<envelopeId>[A-F0-9\s]{32,})/';

    /**
     * The name of the database table for this model.
     *
     * @var string
     */
    protected $table = 'docusign_envelopes';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'amount' => 'float',
        'lost' => 'boolean',
        'sensible_output' => 'array',
        'submitted_at' => 'datetime',
        'submission_error' => 'boolean',
        'internal_cost_transfer' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'envelope_uuid',
        'sofo_form_filename',
        'summary_filename',
    ];

    /**
     * List of valid types and display names for them.
     *
     * @var array<string,string>
     *
     * @phan-read-only
     */
    public static array $types = [
        'purchase_reimbursement' => 'Purchase Reimbursement',
        'travel_reimbursement' => 'Travel Reimbursement',
        'vendor_payment' => 'Vendor Payment',
    ];

    /**
     * The attributes that can be used for filtering in Meilisearch.
     *
     * @var array<string>
     */
    public array $filterable_attributes = [
        'fiscal_year_id',
    ];

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return 'docusign_envelope_id';
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'envelope_uuid';
    }

    /**
     * Get the fiscal year for this envelope.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\FiscalYear, self>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the payee for this envelope, if it is a known user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function payToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pay_to_user_id');
    }

    /**
     * Get the envelope that this envelope replaces, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function replacesEnvelope(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_docusign_envelope_id');
    }

    /**
     * Get the envelope that this envelope replaces, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_docusign_envelope_id');
    }

    /**
     * Get the envelopes that replace this envelope, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<self>
     */
    public function replacedBy(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_docusign_envelope_id');
    }

    /**
     * Get the envelopes that replace this envelope, if any.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<self>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_docusign_envelope_id');
    }

    /**
     * Get the funding sources for this envelope.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\FundingAllocationLine>
     */
    public function fundingSources(): BelongsToMany
    {
        return $this->belongsToMany(FundingAllocationLine::class, 'docusign_funding_sources')
            ->withPivot(['amount'])
            ->withTimestamps()
            ->using(DocuSignFundingSource::class);
    }

    /**
     * Get the attachments associated with the envelope.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the expense report for this envelope, if available.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\ExpenseReport, self>
     */
    public function expenseReport(): BelongsTo
    {
        return $this->belongsTo(ExpenseReport::class);
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

    /**
     * Extract a DocuSign envelope UUID from a summary PDF.
     *
     * @throws CouldNotExtractEnvelopeUuid
     */
    public static function getEnvelopeUuidFromSummaryPdf(string $summary_pdf): string
    {
        $summary_text = (new Parser())
            ->parseContent($summary_pdf)
            ->getText();

        return self::getEnvelopeUuidFromSummaryText($summary_text);
    }

    /**
     * Extract a DocuSign envelope UUID from a summary plaintext.
     *
     * @throws CouldNotExtractEnvelopeUuid
     */
    public static function getEnvelopeUuidFromSummaryText(string $summary_text): string
    {
        $matches = [];

        if (preg_match(self::ENVELOPE_ID_REGEX, $summary_text, $matches) !== 1) {
            throw new CouldNotExtractEnvelopeUuid('Could not extract envelope UUID from provided text');
        }

        $envelope_uuid = str_replace([' ', "\n"], [], $matches['envelopeId']);

        if (strlen($envelope_uuid) !== 32) {
            throw new CouldNotExtractEnvelopeUuid(
                'Could not extract envelope UUID from provided text - candidate string was '
                .strlen($envelope_uuid).' characters'
            );
        }

        return Str::lower(
            Str::substr($envelope_uuid, 0, 8).'-'.
            Str::substr($envelope_uuid, 8, 4).'-'.
            Str::substr($envelope_uuid, 12, 4).'-'.
            Str::substr($envelope_uuid, 16, 4).'-'.
            Str::substr($envelope_uuid, 20, 12)
        );
    }
}
