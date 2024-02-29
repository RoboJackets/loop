<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Providers;

use App\Models\DataSource;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\Menu;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Vyuldashev\NovaPermission\NovaPermissionTool;

class NovaServiceProvider extends NovaApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        Nova::footer(static function (Request $request): string {
            $workday_data_synced_text = 'at an unknown time';

            $workday_timestamp = DataSource::where('name', '=', 'workday')->first()?->synced_at;

            if ($workday_timestamp !== null) {
                $workday_data_synced_text = $workday_timestamp->diffForHumans();
            }

            $engage_data_synced_text = 'at an unknown time';

            $engage_timestamp = DataSource::where('name', '=', 'engage')->first()?->synced_at;

            if ($engage_timestamp !== null) {
                $engage_data_synced_text = $engage_timestamp->diffForHumans();
            }

            return '
<p class="mt-8 text-center text-xs text-80">
    <a class="text-primary dim no-underline" href="https://github.com/RoboJackets/loop">Made with ♥ by RoboJackets</a>
    <span class="px-1">&middot;</span>&nbsp;<span>Workday data synced '.$workday_data_synced_text.'</span>
    <span class="px-1">&middot;</span>&nbsp;<span>Engage data synced '.$engage_data_synced_text.'</span>
</p>
';
        });
        Nova::report(static function (\Throwable $exception): void {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($exception);
            }
        });

        Nova::userMenu(static function (Request $request, Menu $menu): Menu {
            if (
                $request->user()->can('access-quickbooks') &&
                (
                    $request->user()->quickbooks_access_token === null ||
                    (
                        $request->user()->quickbooks_refresh_token_expires_at !== null &&
                        $request->user()->quickbooks_refresh_token_expires_at < Carbon::now()
                    )
                )
            ) {
                $menu->append(
                    MenuItem::externalLink(
                        'Connect to QuickBooks',
                        route('quickbooks.start')
                    )
                );
            }

            return $menu;
        });
    }

    /**
     * Register the Nova routes.
     */
    protected function routes(): void
    {
        Nova::routes()->register();
    }

    /**
     * Register the Nova gate.
     *
     * This gate determines who can access Nova in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', static fn (User $user): bool => $user->can('access-nova'));
    }

    /**
     * Get the dashboards that should be listed in the Nova sidebar.
     *
     * @return array<\Laravel\Nova\Dashboard>
     */
    protected function dashboards(): array
    {
        return [
            new \App\Nova\Dashboards\Main(),
        ];
    }

    /**
     * Get the tools that should be listed in the Nova sidebar.
     *
     * @return array<\Laravel\Nova\Tool>
     */
    public function tools(): array
    {
        return [
            NovaPermissionTool::make()
                ->rolePolicy(RolePolicy::class)
                ->permissionPolicy(PermissionPolicy::class)
                ->canSee(static fn (Request $request): bool => $request->user()->can('update-user-permissions')),
        ];
    }
}
