<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Models\FundingAllocationLine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateFundingAllocationLinesFromJacketPages extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Create Lines from JacketPages';

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
    public $confirmButtonText = 'Create Lines';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'Paste the rows from the Final tab of the bill, without the headers.';

    /**
     * Determine if the filter or action should be available for the given request.
     */
    #[\Override]
    public function authorizedToSee(Request $request): bool
    {
        return $request->user()->can('create-funding-allocations');
    }

    /**
     * Determine if the action is executable for the given request.
     */
    #[\Override]
    public function authorizedToRun(Request $request, $model): bool
    {
        return $request->user()->can('create-funding-allocations');
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\FundingAllocation>  $models
     *
     * @phan-suppress PhanTypeMismatchArgument
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $funding_allocation_id = $models->sole()->id;

        Str::of($fields->lines_from_jacketpages)
            ->explode("\n")
            ->each(static function (string $line, int $key) use ($funding_allocation_id): void {
                $line_parts = Str::of($line)->explode("\t");

                FundingAllocationLine::updateOrCreate(
                    [
                        'funding_allocation_id' => $funding_allocation_id,
                        'line_number' => $line_parts->get(0),
                    ],
                    [
                        'description' => $line_parts->get(1),
                        'amount' => $line_parts->get(5),
                    ]
                );
            });

        return Action::message('Successfully created lines!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<\Laravel\Nova\Fields\Field>
     */
    #[\Override]
    public function fields(NovaRequest $request): array
    {
        return [
            Textarea::make('Lines from JacketPages')
                ->rules('required'),
        ];
    }
}
