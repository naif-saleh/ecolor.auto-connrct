<?php

namespace App\Services;

use App\Models\License;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    /**
     * Get full license information
     *
     * @return array
     */
    public function getLicenseInfo()
    {
        try {
            // Get basic license info
            $licenseData = [
                'status' => License::get('status'),
                'start_date' => License::get('start_date'),
                'end_date' => License::get('end_date'),
                'company_name' => License::get('company_name'),
                'license_key' => License::get('license_key'),
            ];

            // Get module-specific data
            foreach (['auto_distributor_moduales', 'auto_dialer_modules', 'evaluation_moduales'] as $module) {
                $setting = DB::table('licenses')->where('key', $module)->first();

                if ($setting) {
                    $moduleData = json_decode($setting->value, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($moduleData)) {
                        $licenseData[$module] = $moduleData;
                    }
                }
            }

            return $licenseData;
        } catch (\Exception $e) {
            Log::error("Error fetching license information: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Check if license is valid
     *
     * @return bool
     */
    public function isLicenseValid()
    {
        try {
            $licenseStatus = License::get('status');
            $startDate = Carbon::parse(License::get('start_date'));
            $endDate = Carbon::parse(License::get('end_date'));

            return $licenseStatus === 'Active' && now()->between($startDate, $endDate);
        } catch (\Exception $e) {
            Log::error("Error checking license validity: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if license is Active
     *
     * @return bool
     */
    public function isLicenseActive()
    {
        try {
            $licenseStatus = License::get('status');

            return $licenseStatus === 'Active';
        } catch (\Exception $e) {
            Log::error("Error checking license validity: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if license is Expaired
     *
     * @return bool
     */
    public function isLicenseExpaired()
    {
        try {
            $endDate = Carbon::parse(License::get('end_date'));

            return now()->greaterThan($endDate);
        } catch (\Exception $e) {
            Log::error("Error checking if license is expired: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Check if a specific module is enabled and get its settings
     *
     * @param  string  $moduleName
     * @return array|null
     */
    public function getModuleSettings($moduleName)
    {
        if (! $this->isLicenseValid()) {
            return null;
        }

        try {
            $setting = DB::table('licenses')->where('key', $moduleName)->first();

            if (! $setting) {
                return null;
            }

            $moduleData = json_decode($setting->value, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($moduleData)) {
                return null;
            }

            $isEnabled = isset($moduleData['enabled']) ? (bool) $moduleData['enabled'] : false;

            if (! $isEnabled) {
                return null;
            }

            return $moduleData;
        } catch (\Exception $e) {
            Log::error("Error checking module {$moduleName}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Decrement the available agents count in the license
     * when an agent is selected
     *
     * @return void
     */
    public function decrementAgentCount()
    {
        try {
            $dist_setting = DB::table('licenses')->where('key', 'auto_distributor_moduales')->first();

            if ($dist_setting) {
                $dist_data = json_decode($dist_setting->value, true);

                if (is_array($dist_data) && isset($dist_data['max_agents'])) {
                    // Increment provider count
                    $dist_data['max_agents'] = (int) $dist_data['max_agents'] - 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_distributor_moduales')
                        ->update(['value' => json_encode($dist_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Increment the available agents count in the license
     * when an agent is selected
     *
     * @return void
     */
    public function incrementAgentCount()
    {
        try {
            $dist_setting = DB::table('licenses')->where('key', 'auto_distributor_moduales')->first();

            if ($dist_setting) {
                $dist_data = json_decode($dist_setting->value, true);

                if (is_array($dist_data) && isset($dist_data['max_agents'])) {
                    // Increment provider count
                    $dist_data['max_agents'] = (int) $dist_data['max_agents'] + 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_distributor_moduales')
                        ->update(['value' => json_encode($dist_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Get max allowed agents for Auto Distributor
     *
     * @return int
     */
    public function getMaxAutoDistributorAgents()
    {
        $moduleSettings = $this->getModuleSettings('auto_distributor_moduales');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['max_agents']) ? (int) $moduleSettings['max_agents'] : 0;
    }

    /**
     * Get max allowed calls for Auto Distributor
     *
     * @return int
     */
    public function getMaxAutoDistributorCalls()
    {
        $moduleSettings = $this->getModuleSettings('auto_distributor_moduales');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['max_calls']) ? (int) $moduleSettings['max_calls'] : 0;
    }

       /**
     * Get status allowed agents for Auto Distributor
     *
     * @return int
     */
    public function getStatusAutoDistributorCalls()
    {
        $moduleSettings = $this->getModuleSettings('auto_distributor_moduales');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['enabled']) ? (int) $moduleSettings['enabled'] : 0;
    }


     /**
     * decrement the available calls count in the license
     * when a agent is calling
     *
     * @return void
     */
    public function decrementDistCalls()
    {
        try {
            $dist_setting = DB::table('licenses')->where('key', 'auto_distributor_moduales')->first();

            if ($dist_setting) {
                $dist_data = json_decode($dist_setting->value, true);

                if (is_array($dist_data) && isset($dist_data['max_calls'])) {
                    // decrement provider count
                    $dist_data['max_calls'] = (int) $dist_data['max_calls'] - 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_distributor_moduales')
                        ->update(['value' => json_encode($dist_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * check calls count in the license
     * when a agent is calling
     *
     * @return bool
     */
    public function checkDistCallsCount(): bool
    {
        try {
            $setting = DB::table('licenses')->where('key', 'auto_distributor_moduales')->first();

            if (! $setting) {
                return false;
            }

            $data = json_decode($setting->value, true);

            if (! is_array($data)) {
                return false;
            }

            // Ensure max_calls key exists and is a positive integer
            if (isset($data['max_calls']) && (int) $data['max_calls'] > 0) {
                return true;
            }

            // Optional: clean-up or re-save data if needed
            DB::table('licenses')
                ->where('key', 'auto_distributor_moduales')
                ->update(['value' => json_encode($data)]);

        } catch (\Exception $e) {
            report($e);
        }

        return false;
    }

    // Auto Dialer

    /**
     * decrement the available provider count in the license
     * when a provider is deleted
     *
     * @return void
     */
    public function decrementProviderCount()
    {
        try {
            $dialer_setting = DB::table('licenses')->where('key', 'auto_dialer_modules')->first();

            if ($dialer_setting) {
                $dialer_data = json_decode($dialer_setting->value, true);

                if (is_array($dialer_data) && isset($dialer_data['max_providers'])) {
                    // decrement provider count
                    $dialer_data['max_providers'] = (int) $dialer_data['max_providers'] - 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_dialer_modules')
                        ->update(['value' => json_encode($dialer_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Increment the available provider count in the license
     * when a provider is deleted
     *
     * @return void
     */
    public function incrementProviderCount()
    {
        try {
            $dialer_setting = DB::table('licenses')->where('key', 'auto_dialer_modules')->first();

            if ($dialer_setting) {
                $dialer_data = json_decode($dialer_setting->value, true);

                if (is_array($dialer_data) && isset($dialer_data['max_providers'])) {
                    // Increment provider count
                    $dialer_data['max_providers'] = (int) $dialer_data['max_providers'] + 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_dialer_modules')
                        ->update(['value' => json_encode($dialer_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * decrement the available calls count in the license
     * when a provider is calling
     *
     * @return void
     */
    public function decrementDialCalls()
    {
        try {
            $dialer_setting = DB::table('licenses')->where('key', 'auto_dialer_modules')->first();

            if ($dialer_setting) {
                $dialer_data = json_decode($dialer_setting->value, true);

                if (is_array($dialer_data) && isset($dialer_data['max_calls'])) {
                    // decrement provider count
                    $dialer_data['max_calls'] = (int) $dialer_data['max_calls'] - 1;

                    // Update the license data while preserving all other properties
                    DB::table('licenses')
                        ->where('key', 'auto_dialer_modules')
                        ->update(['value' => json_encode($dialer_data)]);
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * check calls count in the license
     * when a provider is calling
     *
     * @return bool
     */
    public function checkDialCallsCount(): bool
    {
        try {
            $setting = DB::table('licenses')->where('key', 'auto_dialer_modules')->first();

            if (! $setting) {
                return false;
            }

            $data = json_decode($setting->value, true);

            if (! is_array($data)) {
                return false;
            }

            // Ensure max_calls key exists and is a positive integer
            if (isset($data['max_calls']) && (int) $data['max_calls'] > 0) {
                return true;
            }

            // Optional: clean-up or re-save data if needed
            DB::table('licenses')
                ->where('key', 'auto_dialer_modules')
                ->update(['value' => json_encode($data)]);

        } catch (\Exception $e) {
            report($e);
        }

        return false;
    }

    /**
     * Get max allowed providers for Auto Dialer
     *
     * @return int
     */
    public function getMaxAutoDialerProvider()
    {
        $moduleSettings = $this->getModuleSettings('auto_dialer_modules');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['max_providers']) ? (int) $moduleSettings['max_providers'] : 0;
    }

    /**
     * Get max allowed providers for Auto Dialer
     *
     * @return int
     */
    public function getMaxAutoDialerCalls()
    {
        $moduleSettings = $this->getModuleSettings('auto_dialer_modules');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['max_calls']) ? (int) $moduleSettings['max_calls'] : 0;
    }

    /**
     * Get status allowed providers for Auto Dialer
     *
     * @return int
     */
    public function getStatusAutoDialerCalls()
    {
        $moduleSettings = $this->getModuleSettings('auto_dialer_modules');

        if (! $moduleSettings) {
            return 0;
        }

        return isset($moduleSettings['enabled']) ? (int) $moduleSettings['enabled'] : 0;
    }
}
