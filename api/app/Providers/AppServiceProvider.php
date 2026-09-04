<?php

namespace App\Providers;

use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Observers\TenantObserver;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingRecord;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        Relation::morphMap([
            'service_user' => ServiceUser::class,
            'safeguarding_case' => SafeguardingCase::class,
            'staff_profile' => StaffProfile::class,
            'training_record' => TrainingRecord::class,
            'compliance_requirement' => ComplianceRequirement::class,
        ]);
    }
}
