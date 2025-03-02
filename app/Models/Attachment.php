<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference

namespace App\Models;

use App\Exceptions\CouldNotExtractEnvelopeUuid;
use App\Observers\AttachmentObserver;
use App\Util\Sentry;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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
 * @property int|null $engage_document_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\DocuSignEnvelope|\App\Models\ExpenseReportLine $attachable
 * @property-read \App\Models\User|null $uploadedBy
 * @property-read string|null $thumbnail_path
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
 * @method static \Illuminate\Database\Eloquent\Builder|Attachment whereEngageDocumentId($value)
 * @method static \Illuminate\Database\Query\Builder|Attachment onlyTrashed()
 * @method static \Illuminate\Database\Query\Builder|Attachment withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Attachment withoutTrashed()
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
#[ObservedBy([AttachmentObserver::class])]
class Attachment extends Model
{
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
        'attachable_id',
        'attachable_type',
        'engage_document_id',
        'filename',
        'workday_comment',
        'workday_instance_id',
        'workday_uploaded_at',
        'workday_uploaded_by_worker_id',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $with = [
        'attachable',
        'uploadedBy',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workday_uploaded_at' => 'datetime',
        ];
    }

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
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string|null>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $filename = $this->filename;

        if (Storage::disk('local')->exists($filename) && Storage::disk('local')->size($filename) > 0) {
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

            try {
                $array['docusign_envelope_uuid'] = DocuSignEnvelope::getEnvelopeUuidFromSummaryText(
                    $array['full_text']
                );
            } catch (CouldNotExtractEnvelopeUuid) {
                $array['docusign_envelope_uuid'] = null;
            }
        } else {
            $array['full_text'] = null;
            $array['docusign_envelope_uuid'] = null;
        }

        return $array;
    }

    public function getThumbnailPathAttribute(): ?string
    {
        $full_file_path = Storage::disk('local')->path($this->filename);

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
}
