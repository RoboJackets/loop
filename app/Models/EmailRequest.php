<?php

declare(strict_types=1);

// phpcs:disable Squiz.WhiteSpace.OperatorSpacing.SpacingBefore
// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference

namespace App\Models;

use App\Traits\GetMorphClassStatic;
use App\Util\Sentry;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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
 * @property string|null $quickbooks_invoice_document_number
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
    use GetMorphClassStatic;
    use Searchable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $fillable = [
        'email_sent_at',
        'fiscal_year_id',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $with = [
        'fiscalYear',
        'expenseReport',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'vendor_document_amount' => 'float',
            'vendor_document_date' => 'date',
            'email_sent_at' => 'datetime',
            'deleted_at' => 'datetime',
            'sensible_output' => 'array',
        ];
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<\App\Models\Attachment, self>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string|null>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $filename = $this->vendor_document_filename;

        if (Storage::disk('local')->exists($filename)) {
            $file_hash = hash_file('sha512', Storage::disk('local')->path($filename));

            Cache::lock(name: 'tika_extraction_'.$file_hash, seconds: 360)->block(
                seconds: 330,
                callback: static function () use ($file_hash, $filename, &$array): void {
                    $array['full_text'] = Cache::rememberForever(
                        'tika_file_'.$file_hash,
                        static fn (): string => Sentry::wrapWithChildSpan(
                            'tika.extract',
                            static fn (): string => (new Client(
                                [
                                    'base_uri' => config('services.tika.url'),
                                    'headers' => [
                                        'Accept' => 'text/plain',
                                        'Content-Type' => 'application/octet-stream',
                                    ],
                                    'allow_redirects' => false,
                                    'connect_timeout' => 10,
                                    'read_timeout' => 60,
                                    'synchronous' => true,
                                ]
                            ))->put(
                                '/tika',
                                [
                                    'body' => Storage::disk('local')->get($filename),
                                ]
                            )->getBody()->getContents()
                        )
                    );
                }
            );
        } else {
            $array['full_text'] = null;
        }

        return $array;
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

    public function getThumbnailPathAttribute(): ?string
    {
        if ($this->vendor_document_filename === null) {
            return null;
        }

        $full_file_path = Storage::disk('local')->path($this->vendor_document_filename);

        if (! file_exists($full_file_path)) {
            return null;
        }

        $extension = str_ends_with(strtolower($full_file_path), '.jpg') ? '.jpg' : '.png';

        $thumbnail_relative_path = '/thumbnail/'.hash_file('sha512', $full_file_path).$extension;

        if (! Storage::disk('public')->exists($thumbnail_relative_path)) {
            return null;
        }

        return $thumbnail_relative_path;
    }

    public function getVendorDocumentThumbnailUrlAttribute(): ?string
    {
        $thumbnail_relative_path = $this->getThumbnailPathAttribute();

        return $thumbnail_relative_path === null ? null : '/storage'.$thumbnail_relative_path;
    }
}
