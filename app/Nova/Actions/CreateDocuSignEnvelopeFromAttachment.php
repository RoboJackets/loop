<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Jobs\SubmitDocuSignEnvelopeToSensible;
use App\Models\DocuSignEnvelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateDocuSignEnvelopeFromAttachment extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Parse DocuSign Envelope';

    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * The text to be used for the action's confirm button.
     *
     * @var string
     */
    public $confirmButtonText = 'Parse DocuSign Envelope';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Are you sure you want to parse this attachment as a DocuSign envelope? Visually confirm '
        .'this is a valid, machine-readable envelope before running this action.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\Attachment>  $models
     */
    public function handle(ActionFields $fields, Collection $models): array
    {
        $attachment = $models->sole();

        $envelope_uuid = DocuSignEnvelope::getEnvelopeUuidFromSummaryPdf(Storage::get($attachment->filename));

        if (DocuSignEnvelope::whereEnvelopeUuid($envelope_uuid)->exists()) {
            return Action::danger('Envelope already exists.');
        }

        $envelope = DocuSignEnvelope::create([
            'envelope_uuid' => $envelope_uuid,
            'sofo_form_filename' => $attachment->filename,
        ]);

        SubmitDocuSignEnvelopeToSensible::dispatch($envelope);

        return Action::visit(route(
            'nova.pages.detail',
            [
                'resource' => \App\Nova\DocuSignEnvelope::uriKey(),
                'resourceId' => $envelope->id,
            ],
            false
        ));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
