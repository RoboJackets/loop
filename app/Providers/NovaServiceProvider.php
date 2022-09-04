<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Providers;

use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        Nova::footer(static fn (Request $request): string => '
<p class="mt-8 text-center text-xs text-80">
    <a class="text-primary dim no-underline" href="https://github.com/RoboJackets/loop">Made with ♥ by RoboJackets</a>
</p>
');
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
