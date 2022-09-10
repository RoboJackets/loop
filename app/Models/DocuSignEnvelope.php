<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\GetMorphClassStatic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

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
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignEnvelope whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|DocuSignEnvelope withTrashed()
 * @method static \Illuminate\Database\Query\Builder|DocuSignEnvelope withoutTrashed()
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class DocuSignEnvelope extends Model
{
    use SoftDeletes;
    use Searchable;
    use GetMorphClassStatic;

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
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'envelope_id',
        'type',
        'description',
        'supplier_name',
        'amount',
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
     * Get the payee for this envelope, if it is a known user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self>
     */
    public function replacesEnvelope(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_docusign_envelope_id');
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
}
