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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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
        // Fail fast on missing critical secrets before any request is served.
        if ($this->app->isProduction()) {
            foreach (['APP_KEY', 'CLINIC_CASE_TOKEN_SECRET', 'CLINIC_SIDECAR_JWT_SECRET'] as $key) {
                if (empty(env($key))) {
                    throw new \RuntimeException(
                        "Required environment variable [{$key}] is not set. Cannot start in production mode."
                    );
                }
            }
        }

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

        // Auto-start queue worker when the web server is running (not during artisan commands)
        if (!$this->app->runningInConsole()) {
            $this->ensureQueueWorkerRunning();
        }

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

    /**
     * Ensure the queue worker process is running. Called on boot and from the manual-start endpoint.
     * Rate-limited via cache when called from boot so it doesn't run on every HTTP request.
     */
    public function ensureQueueWorkerRunning(bool $force = false): void
    {
        // When called from boot(), rate-limit to once per 60 seconds
        if (!$force && !Cache::add('queue.worker.boot_check', 1, 60)) {
            return;
        }

        $pidFile = storage_path('app/queue-worker.pid');

        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));
            if ($pid > 0 && $this->isWorkerProcessRunning($pid)) {
                return; // Worker is already alive
            }
        }

        $this->spawnQueueWorker($pidFile);
    }

    /**
     * Check whether the given PID is still a live PHP process.
     */
    private function isWorkerProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // tasklist returns a row containing the PID if the process exists
            $out = shell_exec("tasklist /FI \"PID eq {$pid}\" /NH 2>&1");
            return $out && stripos($out, 'php') !== false;
        }

        // POSIX: signal 0 checks existence without killing
        return function_exists('posix_kill') && posix_kill($pid, 0);
    }

    /**
     * Spawn a detached queue:work process and record its PID.
     */
    private function spawnQueueWorker(string $pidFile): void
    {
        $php    = PHP_BINARY;
        $artisan = base_path('artisan');

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // PowerShell Start-Process gives us the PID of the new process
                $pidFile = str_replace('/', '\\', $pidFile);
                $cmd = 'powershell -WindowStyle Hidden -Command "'
                    . '$p = Start-Process '
                    . '-FilePath \'' . addslashes($php) . '\' '
                    . '-ArgumentList \'' . addslashes($artisan) . ' queue:work --tries=3 --timeout=300 --sleep=3\' '
                    . '-PassThru -WindowStyle Hidden; '
                    . 'Set-Content -Path \'' . addslashes($pidFile) . '\' -Value $p.Id'
                    . '"';
                pclose(popen($cmd, 'r'));
            } else {
                $log = storage_path('logs/queue-worker.log');
                $pid = shell_exec(
                    sprintf('"%s" "%s" queue:work --tries=3 --timeout=300 --sleep=3 >> "%s" 2>&1 & echo $!', $php, $artisan, $log)
                );
                if ($pid) {
                    file_put_contents($pidFile, trim($pid));
                }
            }

            Log::info('Queue worker auto-started', ['pid_file' => $pidFile]);
        } catch (\Throwable $e) {
            Log::warning('Queue worker auto-start failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Return the current worker status for the UI status endpoint.
     * Called from WorkerStatusController.
     */
    public static function workerStatus(): array
    {
        $pidFile = storage_path('app/queue-worker.pid');

        if (!file_exists($pidFile)) {
            return ['running' => false, 'pid' => null];
        }

        $pid     = (int) trim(file_get_contents($pidFile));
        $running = false;

        if ($pid > 0) {
            if (PHP_OS_FAMILY === 'Windows') {
                $out     = shell_exec("tasklist /FI \"PID eq {$pid}\" /NH 2>&1");
                $running = $out && stripos($out, 'php') !== false;
            } else {
                $running = function_exists('posix_kill') && posix_kill($pid, 0);
            }
        }

        return ['running' => $running, 'pid' => $pid];
    }
}
