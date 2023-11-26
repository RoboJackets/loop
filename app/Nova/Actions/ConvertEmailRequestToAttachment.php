<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Models\Attachment;
use App\Models\EmailRequest;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConvertEmailRequestToAttachment extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Attach to Other Request';

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
    public $confirmButtonText = 'Attach';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Select the email request to which this should be attached.';

    /**
     * Disables action log events for this action.
     *
     * @var bool
     */
    public $withoutActionEvents = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\EmailRequest>  $models
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $this_request = $models->sole();

        Attachment::create([
            'attachable_id' => $fields->other_request_id,
            'attachable_type' => EmailRequest::getMorphClassStatic(),
            'filename' => $this_request->vendor_document_filename,
        ]);

        $this_request->delete();

        return Action::visit(route(
            'nova.pages.detail',
            [
                'resource' => \App\Nova\EmailRequest::uriKey(),
                'resourceId' => $fields->other_request_id,
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
        $resourceId = $request->resourceId ?? $request->resources;

        if ($resourceId === null) {
            return [];
        }

        return [
            Select::make('Email Request', 'other_request_id')
                ->options(
                    static fn (): array => EmailRequest::where('id', '!=', $resourceId)
                        ->get()
                        ->mapWithKeys(static fn (EmailRequest $emailRequest): array => [
                            strval($emailRequest->id) => $emailRequest->id.
                                ' | '.$emailRequest->vendor_document_date->format('Y-m-d').
                                ' | '.$emailRequest->vendor_name.' | $'.
                                number_format(abs($emailRequest->vendor_document_amount), 2),
                        ])
                        ->toArray()
                )
                ->searchable()
                ->required()
                ->rules('required'),
        ];
    }
}
