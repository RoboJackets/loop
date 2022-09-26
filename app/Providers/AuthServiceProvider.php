<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Attachment;
use App\Models\DocuSignEnvelope;
use App\Models\ExternalCommitteeMember;
use App\Models\FiscalYear;
use App\Models\FundingAllocation;
use App\Models\FundingAllocationLine;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\DocuSignEnvelopePolicy;
use App\Policies\ExternalCommitteeMemberPolicy;
use App\Policies\FiscalYearPolicy;
use App\Policies\FundingAllocationLinePolicy;
use App\Policies\FundingAllocationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookCallPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\WebhookClient\Models\WebhookCall;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Attachment::class => AttachmentPolicy::class,
        DocuSignEnvelope::class => DocuSignEnvelopePolicy::class,
        ExternalCommitteeMember::class => ExternalCommitteeMemberPolicy::class,
        FiscalYear::class => FiscalYearPolicy::class,
        FundingAllocation::class => FundingAllocationPolicy::class,
        FundingAllocationLine::class => FundingAllocationLinePolicy::class,
        Permission::class => PermissionPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
        WebhookCall::class => WebhookCallPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
