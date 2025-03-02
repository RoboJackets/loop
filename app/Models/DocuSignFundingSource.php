<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for associating a DocuSign envelope to a funding allocation line.
 *
 * @property int $id
 * @property int $docusign_envelope_id
 * @property int $funding_allocation_line_id
 * @property float|null $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource query()
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereDocusignEnvelopeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereFundingAllocationLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DocuSignFundingSource whereUpdatedAt($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class DocuSignFundingSource extends Pivot
{
    /**
     * The name of the database table for this model.
     *
     * @var string
     *
     * @phan-read-only
     */
    public $table = 'docusign_funding_sources';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     *
     * @phan-read-only
     */
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}
