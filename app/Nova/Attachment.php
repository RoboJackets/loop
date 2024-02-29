<?php

declare(strict_types=1);

// phpcs:disable PSR2.Methods.FunctionCallSignature.SpaceBeforeOpenBracket

namespace App\Nova;

use App\Nova\Actions\MatchAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * A Nova resource for attachments.
 *
 * @extends \App\Nova\Resource<\App\Models\Attachment>
 *
 * @phan-suppress PhanUnreferencedClass
 */
class Attachment extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Attachment::class;

    /**
     * The columns that should be searched.
     *
     * @var array<string>
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()
                ->sortable(),

            MorphTo::make('Attachable'),

            Avatar::make('Thumbnail', fn (): ?string => $this->thumbnail_path)
                ->disk('public')
                ->squared()
                ->disableDownload(),

            Text::make('Filename')
                ->displayUsing(static function (string $filename): string {
                    $array = explode('/', $filename);

                    return end($array);
                })
                ->onlyOnIndex(),

            File::make('File', 'filename')
                ->disk('local'),

            Panel::make('Workday Metadata', [
                Number::make('Instance ID', 'workday_instance_id')
                    ->canSee(static fn (Request $request): bool => $request->user()->can('access-workday'))
                    ->onlyOnDetail(),

                BelongsTo::make('Uploaded By', 'uploadedBy', User::class)
                    ->onlyOnDetail(),

                DateTime::make('Uploaded At', 'workday_uploaded_at')
                    ->onlyOnDetail(),

                Text::make('Comment', 'workday_comment')
                    ->onlyOnDetail(),
            ]),

            Panel::make('Engage Metadata', [
                Number::make('Document ID', 'engage_document_id')
                    ->onlyOnDetail(),
            ]),

            Panel::make('Timestamps', [
                DateTime::make('Created', 'created_at')
                    ->onlyOnDetail(),

                DateTime::make('Last Updated', 'updated_at')
                    ->onlyOnDetail(),

                DateTime::make('Deleted', 'deleted_at')
                    ->onlyOnDetail(),
            ]),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<\Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<\Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<\Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<\Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        $resourceId = $request->resourceId ?? $request->resources;

        if ($resourceId === null) {
            return [];
        }

        $attachment = \App\Models\Attachment::where('id', '=', $resourceId)->sole();

        if (
            $attachment->attachable_type === \App\Models\ExpenseReportLine::getMorphClassStatic() &&
            str_ends_with(strtolower($attachment->filename), '.pdf') &&
            Storage::disk('local')->exists($attachment->filename)
        ) {
            return [
                MatchAttachment::make()
                    ->canSee(static fn (NovaRequest $request): true => true)
                    ->canRun(static fn (NovaRequest $request, \App\Models\Attachment $attachment): true => true),
            ];
        }

        return [];
    }

    /**
     * Get the value that should be displayed to represent the resource.
     */
    public function title(): string
    {
        $array = explode('/', $this->filename);

        return end($array);
    }

    /**
     * Get the search result subtitle for the resource.
     */
    public function subtitle(): string
    {
        if ($this->attachable_type === 'docusign-envelope') {
            return 'DocuSign | '.$this->attachable->submitted_at->format('Y-m-d').' | '.$this->attachable->description;
        } elseif ($this->attachable_type === 'expense-report-line') {
            return 'Workday | '.$this->workday_uploaded_at->format('Y-m-d').' | '
            .$this->attachable->expenseReport->memo;
        } elseif ($this->attachable_type === 'engage-purchase-request') {
            return 'Engage | '.$this->attachable->submitted_at->format('Y-m-d').' | '.$this->attachable->subject;
        } else {
            throw new \Exception('Unknown attachable_type '.$this->attachable_type);
        }
    }
}
