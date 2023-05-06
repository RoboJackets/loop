<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireTernaryOperator.TernaryOperatorNotUsed

namespace App\Nova\Actions;

use App\Jobs\SubmitDocuSignEnvelopeToSensible;
use App\Models\DocuSignEnvelope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class UploadDocuSignEnvelope extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Upload DocuSign Envelope';

    /**
     * Indicates if this action is only available on the resource index view.
     *
     * @var bool
     */
    public $onlyOnIndex = true;

    /**
     * Indicates if the action can be run without any models.
     *
     * @var bool
     */
    public $standalone = true;

    /**
     * The text to be used for the action's confirm button.
     *
     * @var string
     */
    public $confirmButtonText = 'Upload';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Provide just the SOFO form as a PDF, and the DocuSign envelope UUID in any format.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\DocuSignEnvelope>  $models
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $envelope = new DocuSignEnvelope();
        if (Str::contains($fields->envelope_uuid, '-')) {
            $envelope->envelope_uuid = $fields->envelope_uuid;
        } else {
            $envelope->envelope_uuid = Str::substr($fields->envelope_uuid, 0, 8).'-'.
                Str::substr($fields->envelope_uuid, 8, 4).'-'.
                Str::substr($fields->envelope_uuid, 12, 4).'-'.
                Str::substr($fields->envelope_uuid, 16, 4).'-'.
                Str::substr($fields->envelope_uuid, 20, 12);
        }

        if (DocuSignEnvelope::whereEnvelopeUuid($envelope->envelope_uuid)->exists()) {
            return Action::danger('Envelope already exists.');
        }

        $envelope->sofo_form_filename = 'docusign/'.$envelope->envelope_uuid.'/sofo.pdf';
        $envelope->save();

        Storage::makeDirectory('docusign/'.$envelope->envelope_uuid);

        Storage::disk('local')
            ->put(
                'docusign/'.$envelope->envelope_uuid.'/sofo.pdf',
                $fields->sofo_form->get()
            );

        SubmitDocuSignEnvelopeToSensible::dispatch($envelope);

        return self::visit(route(
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
        return [
            File::make('SOFO Form')
                ->required(),

            Text::make('Envelope UUID')
                ->required(),
        ];
    }
}
