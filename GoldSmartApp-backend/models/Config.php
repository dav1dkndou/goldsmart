<?php declare(strict_types=1);

/** Config Model */
require_once __DIR__ . '/../core/Model.php';

class Config extends Model
{
    protected string $table = 'configs';
    protected string $primaryKey = 'config_key';

    /**
     * Get config value
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $config = $this->findOne(['config_key' => $key]);
        return $config ? $config['config_value'] : $default;
    }

    /**
     * Set config value
     */
    public function setValue(string $key, mixed $value): string|bool
    {
        $existing = $this->findOne(['config_key' => $key]);

        if ($existing) {
            $sql = "UPDATE {$this->table} SET config_value = :value, updated_at = :updated_at WHERE config_key = :key LIMIT 1";
            return $this->execute($sql, [
                'value' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
                'key' => $key
            ]);
        } else {
            return $this->create([
                'config_key' => $key,
                'config_value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    /**
     * Get all configs as array
     */
    public function getAllAsArray(): array
    {
        $configs = $this->findAll();
        $result = [];

        foreach ($configs as $config) {
            $result[$config['config_key']] = $config['config_value'];
        }

        return $result;
    }

    /**
     * Get app config for mobile
     */
    public function getAppConfig(): array
    {
        $configs = $this->getAllAsArray();

        return [
            'gc' => [
                'current_price' => (float) ($configs['gc_price'] ?? 10000),
            ],
            'withdrawal' => [
                'admin_fee' => (float) ($configs['withdrawal_fee'] ?? 7000),
                'min_amount' => (float) ($configs['min_withdrawal'] ?? 50),
            ],
            'referral' => [
                'signup_bonus' => (float) ($configs['referral_bonus'] ?? 5),
                'commission_bonus' => (float) ($configs['commission_bonus'] ?? 0.5),
            ],
            'referral_bonus' => (float) ($configs['referral_bonus'] ?? 5),
            'commission_bonus' => (float) ($configs['commission_bonus'] ?? 0.5),
            'maintenance_mode' => ($configs['maintenance_mode'] ?? '0') === '1',
            'ads' => [
                'enabled' => ($configs['ads_enabled'] ?? '1') === '1',
                'show_before_withdrawal' => ($configs['ads_show_before_withdrawal'] ?? '1') === '1',
                'show_before_purchase' => ($configs['ads_show_before_purchase'] ?? '1') === '1',
                'show_before_bonus' => ($configs['ads_show_before_bonus'] ?? '1') === '1',
                'units' => [
                    'banner_1' => $configs['ad_banner_1'] ?? '',
                    'banner_2' => $configs['ad_banner_2'] ?? '',
                    'rewarded' => $configs['ad_rewarded'] ?? '',
                    'interstitial' => $configs['ad_interstitial'] ?? '',
                    'interstitial_bonus' => $configs['ad_interstitial_bonus'] ?? '',
                    'native' => $configs['ad_native'] ?? '',
                    'rewarded_bonus_1' => $configs['ad_rewarded_bonus_1'] ?? '',
                    'rewarded_bonus_2' => $configs['ad_rewarded_bonus_2'] ?? '',
                    'rewarded_bonus_3' => $configs['ad_rewarded_bonus_3'] ?? '',
                ],
            ],
        ];
    }
}
