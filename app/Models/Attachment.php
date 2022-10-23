<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch

namespace App\Models;

use App\Exceptions\CouldNotExtractEnvelopeUuid;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;

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

        $response = (new Client(
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
                'body' => Storage::disk('local')->get($this->filename),
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new \Exception(
                'Tika returned non-200 status code - '.$response->getStatusCode().' - '
                .$response->getBody()->getContents()
            );
        }

        $array['full_text'] = $response->getBody()->getContents();

        try {
            $array['docusign_envelope_uuid'] = DocuSignEnvelope::getEnvelopeUuidFromSummaryText($array['full_text']);
        } catch (CouldNotExtractEnvelopeUuid) {
            // do nothing
        }

        return $array;
    }
}
