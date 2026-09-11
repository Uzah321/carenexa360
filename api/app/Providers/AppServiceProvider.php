<?php

namespace App\Providers;

use App\Mail\Transport\MicrosoftGraphTransport;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Observers\TenantObserver;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingRecord;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Tenant::observe(TenantObserver::class);

        Mail::extend('msgraph', fn () => new MicrosoftGraphTransport(
            tenantId: (string) config('services.msgraph.tenant_id'),
            clientId: (string) config('services.msgraph.client_id'),
            clientSecret: (string) config('services.msgraph.client_secret'),
            fromAddress: (string) config('services.msgraph.from_address'),
        ));

        Relation::morphMap([
            'service_user' => ServiceUser::class,
            'safeguarding_case' => SafeguardingCase::class,
            'staff_profile' => StaffProfile::class,
            'training_record' => TrainingRecord::class,
            'compliance_requirement' => ComplianceRequirement::class,
        ]);
    }
}
