<?php

namespace Tests\Unit;

use App\Support\LabelManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LabelManagerTest extends TestCase
{
    public function test_catalog_contains_current_default_labels(): void
    {
        $this->assertSame([
            'supplier' => ['singular' => 'Supplier', 'plural' => 'Suppliers'],
            'customer' => ['singular' => 'Customer', 'plural' => 'Customers'],
            'article' => ['singular' => 'Article', 'plural' => 'Articles'],
            'invoice' => ['singular' => 'Invoice', 'plural' => 'Invoices'],
            'order' => ['singular' => 'Order', 'plural' => 'Orders'],
            'shipment' => ['singular' => 'Shipment', 'plural' => 'Shipments'],
            'payment' => ['singular' => 'Payment', 'plural' => 'Payments'],
            'purchase' => ['singular' => 'Purchase', 'plural' => 'Purchases'],
            'report' => ['singular' => 'Report', 'plural' => 'Reports'],
            'stock' => ['singular' => 'Stock', 'plural' => 'Stock'],
            'dashboard' => ['singular' => 'Dashboard', 'plural' => 'Dashboards'],
        ], config('labels'));
    }

    public function test_returns_default_singular_label(): void
    {
        $this->assertSame('Supplier', app(LabelManager::class)->get('supplier'));
    }

    public function test_returns_default_plural_label(): void
    {
        $this->assertSame('Suppliers', app(LabelManager::class)->get('supplier', 'plural'));
    }

    public function test_missing_key_returns_human_readable_fallback(): void
    {
        $this->assertSame(
            'Payment Program',
            app(LabelManager::class)->get('payment_program')
        );
    }

    public function test_missing_form_falls_back_to_singular(): void
    {
        $this->assertSame(
            'Invoice',
            app(LabelManager::class)->get('invoice', 'short')
        );
    }

    public function test_empty_configured_value_falls_back_safely(): void
    {
        Config::set('labels.customer.plural', '   ');

        $this->assertSame(
            'Customer',
            app(LabelManager::class)->get('customer', 'plural')
        );
    }

    public function test_returns_custom_config_override(): void
    {
        Config::set('labels.supplier.singular', 'Vendor');
        Config::set('labels.supplier.plural', 'Vendors');

        $manager = app(LabelManager::class);

        $this->assertSame('Vendor', $manager->get('supplier'));
        $this->assertSame('Vendors', $manager->get('supplier', 'plural'));
    }

    public function test_manager_is_registered_as_a_singleton(): void
    {
        $this->assertSame(
            app(LabelManager::class),
            app(LabelManager::class)
        );
    }
}
