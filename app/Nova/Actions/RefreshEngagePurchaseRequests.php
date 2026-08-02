<?php

declare(strict_types=1);

namespace App\Nova\Actions;

use App\Jobs\SyncEngage;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class RefreshEngagePurchaseRequests extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Refresh Engage Requests';

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
    public $confirmButtonText = 'Refresh';

    /**
     * The text to be used for the action's confirmation text.
     *
     * @var string
     */
    public $confirmText = 'This action will refresh purchase requests and attachments from Engage, and may take a '
        .'minute to process.';

    /**
     * Perform the action on the given models.
     *
     * @param  \Illuminate\Support\Collection<int,\App\Models\EngagePurchaseRequest>  $models
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        SyncEngage::dispatchSync();

        return Action::message('All Engage requests refreshed!');
    }
}
