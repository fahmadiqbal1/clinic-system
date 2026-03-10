<?php

namespace App\Providers;

use App\Models\AiAnalysis;
use App\Models\DoctorPayout;
use App\Models\StaffContract;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\ProcurementRequest;
use App\Models\TriageVital;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Observers\InvoiceObserver;
use App\Observers\PatientObserver;
use App\Observers\PrescriptionObserver;
use App\Policies\AiAnalysisPolicy;
use App\Policies\DoctorPayoutPolicy;
use App\Policies\StaffContractPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PatientPolicy;
use App\Policies\PrescriptionPolicy;
use App\Policies\ProcurementRequestPolicy;
use App\Policies\TriageVitalPolicy;
use App\Policies\UserPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\StockMovementPolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers for centralized side effects
        Invoice::observe(InvoiceObserver::class);
        Patient::observe(PatientObserver::class);
        Prescription::observe(PrescriptionObserver::class);

        // Register policies
        Gate::policy(AiAnalysis::class, AiAnalysisPolicy::class);
        Gate::policy(DoctorPayout::class, DoctorPayoutPolicy::class);
        Gate::policy(StaffContract::class, StaffContractPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
        Gate::policy(TriageVital::class, TriageVitalPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(InventoryItem::class, InventoryItemPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(ProcurementRequest::class, ProcurementRequestPolicy::class);

        // Dashboard analytics gates
        Gate::define('viewExpenseIntelligence', function (User $user) {
            return $user->hasRole('Owner');
        });

        Gate::define('viewInventoryHealth', function (User $user) {
            return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology']);
        });

        Gate::define('viewLowStockAlerts', function (User $user) {
            return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology']);
        });

        Gate::define('viewProcurementPipeline', function (User $user) {
            return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']);
        });
    }
}
