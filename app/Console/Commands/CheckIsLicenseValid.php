<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckIsLicenseValid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-is-license-valid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    /**
     * LicenseService Service
     *
     * @var LicenseService
     */
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        parent::__construct();
        $this->licenseService = $licenseService;
    }

    public function handle()
    {
        $license_key = License::get('license_key');
        try {
            // Send POST request to external licensing API
            $response = Http::post('http://127.0.0.1:8001/api/licens.ecolor/allow-license-key', [
                'license_key' => $license_key,
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Extract nested license data
                $licenseData = $responseData['data'];

                // Save to License model
                License::set('company_name', $licenseData['company_name'] ?? 'false', 'Company Name');
                License::set('license_key', $licenseData['license_key'] ?? 'false', 'License Key');
                License::set('start_date', $licenseData['start_date'] ?? 'false', 'Start Date');
                License::set('end_date', $licenseData['end_date'] ?? 'false', 'End Date');
                License::set('status', $licenseData['status'] ?? 'false', 'License Status');

                // JSON-encode module arrays before saving
                // Auto Dialer Modules
                $remoteADModules = $licenseData['license']['auto_dialer_modules'] ?? null;
                if ($remoteADModules) {
                    $localADModules = json_decode(License::get('auto_dialer_modules'), true) ?? [];
                    foreach ($remoteADModules as $key => $value) {
                        if (isset($localADModules[$key])) {
                            $remoteADModules[$key] = min($value, $localADModules[$key]);
                        }
                    }
                    License::set('auto_dialer_modules', json_encode($remoteADModules), 'auto_dialer_modules');
                } else {
                    License::set('auto_dialer_modules', 'false', 'auto_dialer_modules');
                }

                // Auto Distributor Modules
                $remoteDistModules = $licenseData['license']['auto_distributor_moduales'] ?? null;
                if ($remoteDistModules) {
                    $localDistModules = json_decode(License::get('auto_distributor_moduales'), true) ?? [];
                    foreach ($remoteDistModules as $key => $value) {
                        if (isset($localDistModules[$key])) {
                            $remoteDistModules[$key] = min($value, $localDistModules[$key]);
                        }
                    }
                    License::set('auto_distributor_moduales', json_encode($remoteDistModules), 'auto_distributor_moduales');
                } else {
                    License::set('auto_distributor_moduales', 'false', 'auto_distributor_moduales');
                }

                // Evaluation Modules
                $remoteEvalModules = $licenseData['license']['evaluation_moduales'] ?? null;
                if ($remoteEvalModules) {
                    $localEvalModules = json_decode(License::get('evaluation_moduales'), true) ?? [];
                    foreach ($remoteEvalModules as $key => $value) {
                        if (isset($localEvalModules[$key])) {
                            $remoteEvalModules[$key] = min($value, $localEvalModules[$key]);
                        }
                    }
                    License::set('evaluation_moduales', json_encode($remoteEvalModules), 'evaluation_moduales');
                } else {
                    License::set('evaluation_moduales', 'false', 'evaluation_moduales');
                }

                Log::info('License Updated Successfully');

            }

        } catch (\Exception $e) {
            Log::info('License Erorr: '.$e->getMessage());
        }

    }
}
