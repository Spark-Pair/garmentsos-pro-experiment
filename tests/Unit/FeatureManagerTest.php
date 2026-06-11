<?php

namespace Tests\Unit;

use App\Support\FeatureManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    public function test_current_module_defaults_are_enabled(): void
    {
        $enabledFeatures = [
            'suppliers',
            'customers',
            'articles',
            'orders',
            'invoices',
            'shipments',
            'stock',
            'payments',
            'payment_programs',
            'bank_accounts',
            'vouchers',
            'expenses',
            'fabrics',
            'production',
            'employees',
            'attendance',
            'daily_ledger',
            'cr_dr',
            'sales_returns',
            'logistics',
            'utilities',
            'reports',
            'backups',
        ];

        $manager = app(FeatureManager::class);

        foreach ($enabledFeatures as $feature) {
            $this->assertTrue($manager->enabled($feature), $feature);
        }
    }

    public function test_reserved_feature_defaults_are_disabled(): void
    {
        $disabledFeatures = [
            'purchases',
            'barcode',
            'advanced_reports',
            'dashboard_cards',
            'updates',
        ];

        $manager = app(FeatureManager::class);

        foreach ($disabledFeatures as $feature) {
            $this->assertFalse($manager->enabled($feature), $feature);
        }
    }

    public function test_missing_feature_is_disabled(): void
    {
        $this->assertFalse(app(FeatureManager::class)->enabled('missing_feature'));
    }

    public function test_disabled_is_the_inverse_of_enabled(): void
    {
        $manager = app(FeatureManager::class);

        $this->assertFalse($manager->disabled('suppliers'));
        $this->assertTrue($manager->disabled('barcode'));
        $this->assertTrue($manager->disabled('missing_feature'));
    }

    public function test_all_returns_normalized_feature_catalog(): void
    {
        $manager = app(FeatureManager::class);
        $features = $manager->all();

        $this->assertSame(array_keys(config('features')), array_keys($features));
        $this->assertSame(true, $features['suppliers']);
        $this->assertSame(false, $features['updates']);
    }

    public function test_runtime_config_override_can_enable_a_feature(): void
    {
        Config::set('features.updates', true);

        $this->assertTrue(app(FeatureManager::class)->enabled('updates'));
    }

    public function test_runtime_config_override_can_disable_a_feature(): void
    {
        Config::set('features.shipments', false);

        $this->assertFalse(app(FeatureManager::class)->enabled('shipments'));
    }

    public function test_invalid_values_are_normalized_to_disabled(): void
    {
        Config::set('features.string_value', 'true');
        Config::set('features.array_value', ['enabled' => true]);
        Config::set('features.null_value', null);

        $manager = app(FeatureManager::class);

        $this->assertFalse($manager->enabled('string_value'));
        $this->assertFalse($manager->enabled('array_value'));
        $this->assertFalse($manager->enabled('null_value'));

        $features = $manager->all();

        $this->assertSame(false, $features['string_value']);
        $this->assertSame(false, $features['array_value']);
        $this->assertSame(false, $features['null_value']);
    }

    public function test_manager_is_registered_as_a_singleton(): void
    {
        $this->assertSame(
            app(FeatureManager::class),
            app(FeatureManager::class)
        );
    }
}
