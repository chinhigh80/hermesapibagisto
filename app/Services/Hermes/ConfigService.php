<?php

namespace App\Services\Hermes;

use Webkul\Setting\Repositories\SettingRepository;
use Illuminate\Support\Facades\Config;
use Exception;

class ConfigService
{
    protected $settingRepository;

    public function __construct(SettingRepository $settingRepository)
    {
        $this->settingRepository = $settingRepository;
    }

    /**
     * Update store configuration.
     *
     * @param array $data ['store_name' => string, 'currency' => string, 'locale' => string, 'timezone' => string, 'tax_settings' => array]
     * @return array
     */
    public function update(array $data)
    {
        $updated = [];

        // Store name
        if (isset($data['store_name'])) {
            $this->settingRepository->update(['shop.name' => $data['store_name']]);
            $updated[] = 'store_name';
        }

        // Currency
        if (isset($data['currency'])) {
            $this->settingRepository->update(['currency' => $data['currency']]);
            $updated[] = 'currency';
        }

        // Locale (default locale)
        if (isset($data['locale'])) {
            $this->settingRepository->update(['locale' => $data['locale']]);
            $updated[] = 'locale';
        }

        // Timezone
        if (isset($data['timezone'])) {
            $this->settingRepository->update(['timezone' => $data['timezone']]);
            $updated[] = 'timezone';
        }

        // Tax settings (if provided as array)
        if (isset($data['tax_settings']) && is_array($data['tax_settings'])) {
            foreach ($data['tax_settings'] as $key => $value) {
                // Assuming tax settings are stored under 'tax.' prefix
                $this->settingRepository->update(['tax.' . $key => $value]);
                $updated[] = "tax.{$key}";
            }
        }

        // Clear config cache to reflect changes
        \Artisan::call('config:clear');

        return ['updated' => $updated];
    }
}