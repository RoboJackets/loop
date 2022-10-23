<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch

namespace App\Models;

use App\Exceptions\CouldNotExtractEnvelopeUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Smalot\PdfParser\Parser;

/**
 * An attachment for a DocuSign envelope.
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property string $filename
 * @property int|null $workday_instance_id
 * @property int|null $workday_uploaded_by_worker_id
 * @property \Illuminate\Support\Carbon|null $workday_uploaded_at
 * @property string|null $workday_comment
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\DocuSignEnvelope|\App\Models\ExpenseReportLine $attachable
 * @property-read \App\Models\User|null $uploadedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereAttachableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereAttachableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereWorkdayComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereWorkdayInstanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereWorkdayUploadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereWorkdayUploadedByWorkerId($value)
 * @method static \Illuminate\Database\Query\Builder|Attachment onlyTrashed()
 * @method static \Illuminate\Database\Query\Builder|Attachment withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Attachment withoutTrashed()
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class Attachment extends Model
{
    use SoftDeletes;
    use Searchable;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'workday_uploaded_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'filename',
        'workday_instance_id',
        'workday_uploaded_by_worker_id',
        'workday_uploaded_at',
        'workday_comment',
    ];

    /**
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'filename',
        'workday_comment',
        'docusign_envelope_uuid',
        'full_text',
    ];

    /**
     * Get all the owning payable models.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo<\App\Models\DocuSignEnvelope,\App\Models\Attachment>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Return the user that uploaded this attachment to Workday, if applicable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, self>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'workday_uploaded_by_worker_id', 'workday_instance_id');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'workday_instance_id';
    }

    private function getMimeType(): ?string
    {
        $output = null;

        // --mime-type to get just the MIME type
        // --brief to be "brief" and return *just* the MIME type
        exec('file --mime-type --brief '.escapeshellarg($this->filename), $output);

        if (count($output) === 0) {
            return null;
        }

        $output = $output[0];

        // Sanity check to make sure we got a MIME type back, rather than an error (file names can't contain the /
        // character so that was a good indicator)
        if ($output !== null && str_contains($output, '/') && ! str_contains($output, 'cannot open')) {
            return $output;
        }

        return null;
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['full_text'] = null;
        $array['docusign_envelope_uuid'] = null;

        $mime_type = $this->getMimeType();

        if ($mime_type === 'application/pdf') {
            $array['full_text'] = (new Parser())->parseFile($this->filename)->getText();
            try {
                $array['docusign_envelope_uuid'] = DocuSignEnvelope::getEnvelopeUuidFromSummaryPdf($array['full_text']);
            } catch (CouldNotExtractEnvelopeUuid) {
                // do nothing
            }
        }

        return $array;
    }
}
