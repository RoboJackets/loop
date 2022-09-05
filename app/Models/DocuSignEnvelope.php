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
        'sensible_response' => 'array',
        'submitted_at' => 'datetime',
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
