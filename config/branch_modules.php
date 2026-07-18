<?php

$system = [
    'configurable_by_developer' => true,
    'branchable' => false,
    'supports_branch_selector' => false,
    'supports_multi_branch_selector' => false,
    'supports_record_filtering' => false,
    'can_filter_records' => false,
    'has_branch_id_support' => false,
    'supports_branch_branding' => false,
    'can_use_branch_branding' => false,
    'supports_serial_prefix' => false,
    'supports_branch_serial_prefix' => false,
    'supports_doc_identity_prefix' => false,
    'safe_default_enabled' => false,
    'is_system_module' => true,
    'notes' => 'System page. Developer can enable/disable visibility, but branch switching is not used for this page.',
];

$configurable = [
    'configurable_by_developer' => true,
    'branchable' => true,
    'supports_branch_selector' => true,
    'supports_multi_branch_selector' => false,
    'supports_record_filtering' => false,
    'can_filter_records' => false,
    'has_branch_id_support' => false,
    'supports_branch_branding' => false,
    'can_use_branch_branding' => false,
    'supports_serial_prefix' => false,
    'supports_branch_serial_prefix' => false,
    'supports_doc_identity_prefix' => false,
    'safe_default_enabled' => false,
    'is_system_module' => false,
    'notes' => 'Branch switching available, but record filtering requires branch_id support/review.',
];

$filterReady = array_merge($configurable, [
    'supports_record_filtering' => true,
    'can_filter_records' => true,
    'has_branch_id_support' => true,
    'notes' => 'Branch switching and record filtering are available.',
]);

$document = array_merge($filterReady, [
    'supports_branch_branding' => true,
    'can_use_branch_branding' => true,
    'supports_multi_branch_selector' => true,
    'supports_serial_prefix' => true,
    'supports_branch_serial_prefix' => true,
    'supports_doc_identity_prefix' => true,
]);

$report = array_merge($configurable, [
    'supports_branch_branding' => true,
    'can_use_branch_branding' => true,
    'notes' => 'Report branch switching is available. Report query filtering must be reviewed per report.',
]);

$multiReport = array_merge($report, [
    'supports_multi_branch_selector' => true,
]);

return [
    'ignored_route_prefixes' => [
        '_ignition',
        'api',
        'sanctum',
        'add-rate',
        'change-data-layout',
        'get-category-data',
        'get-employees-by-category',
        'get-order-details',
        'get-payments-by-method',
        'get-program-details',
        'get-shipment-details',
        'get-utility-accounts',
        'get-voucher-details',
        'payment-programs.update-program',
        'print-invoices',
        'set-cr-type',
        'set-daily-ledger-type',
        'set-invoice-type',
        'set-physical-quantity-report-type',
        'set-production-type',
        'set-statement-type',
        'set-voucher-type',
        'update-bank-account-status',
        'update-employee-status',
        'update-image',
        'update-last-activity',
        'update-supplier-category',
        'update-theme',
        'update-user-status',
        'update-menu-shortcuts',
        'users.reset-password',
        'module-branch-preferences',
        'branch-logos',
    ],

    'aliases' => [
        'dashboard' => 'home',
        'cargo' => 'cargos',
        'statements' => 'reports_statement',
        'pending_payments' => 'reports_pending_payments',
        'bank_transactions' => 'daily_ledger',
        'branches' => 'developer_branches',
        'backups' => 'developer_backups',
        'backup_db' => 'developer_backups',
        'license' => 'developer_license',
        'updater' => 'developer_updater',
        'permission_report' => 'permissions_report',
        'setup_wizard' => 'first_run_setup',
        'auth' => 'auth_login',
        'subscription' => 'subscription_expired',
        'settings' => 'setup',
    ],

    'modules' => [
        'home' => array_merge($system, ['label' => 'Home / Dashboard', 'group' => 'Core', 'page_reference' => 'home']),
        'dashboard' => array_merge($system, ['label' => 'Dashboard', 'group' => 'Core', 'page_reference' => 'home', 'notes' => 'Dashboard alias kept for existing settings compatibility.']),
        'notifications' => array_merge($system, ['label' => 'Notifications', 'group' => 'Core', 'page_reference' => 'notifications']),

        'customers' => array_merge($filterReady, ['label' => 'Customers', 'group' => 'Parties / People', 'page_reference' => 'customers']),
        'suppliers' => array_merge($filterReady, ['label' => 'Suppliers', 'group' => 'Parties / People', 'page_reference' => 'suppliers']),
        'employees' => array_merge($filterReady, ['label' => 'Employees / Workers', 'group' => 'Parties / People', 'page_reference' => 'employees']),
        'users' => array_merge($filterReady, ['label' => 'Users', 'group' => 'Parties / People', 'page_reference' => 'users']),

        'articles' => array_merge($filterReady, ['label' => 'Articles', 'group' => 'Articles / Setup', 'page_reference' => 'articles', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'safe_default_enabled' => true]),
        'rates' => array_merge($filterReady, ['label' => 'Rates', 'group' => 'Articles / Setup', 'page_reference' => 'rates']),
        'setups' => array_merge($filterReady, ['label' => 'Setups / Categories', 'group' => 'Articles / Setup', 'page_reference' => 'setups']),
        'setup' => array_merge($filterReady, ['label' => 'Setup / Categories', 'group' => 'Articles / Setup', 'page_reference' => 'setups', 'notes' => 'Friendly setup module key for Branch Settings.']),
        'fabrics' => array_merge($filterReady, ['label' => 'Fabrics', 'group' => 'Articles / Setup', 'page_reference' => 'fabrics']),

        'orders' => array_merge($document, ['label' => 'Orders', 'group' => 'Sales', 'page_reference' => 'orders', 'doc_identity_prefix' => 'O', 'safe_default_enabled' => true, 'dependencies' => 'Recommended with Articles, Customers, Invoices, Customer Payments, Reports.']),
        'invoices' => array_merge($document, ['label' => 'Invoices', 'group' => 'Sales', 'page_reference' => 'invoices', 'doc_identity_prefix' => 'I', 'safe_default_enabled' => true, 'dependencies' => 'Recommended with Orders, Shipments, Customer Payments, Reports.']),
        'sales_returns' => array_merge($filterReady, ['label' => 'Sales Returns', 'group' => 'Sales', 'page_reference' => 'sales-returns', 'supports_branch_branding' => true, 'can_use_branch_branding' => true]),
        'customer_payments' => array_merge($filterReady, ['label' => 'Customer Payments', 'group' => 'Sales', 'page_reference' => 'customer-payments', 'supports_branch_branding' => true, 'can_use_branch_branding' => true, 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'CP']),
        'payment_programs' => array_merge($filterReady, ['label' => 'Payment Programs', 'group' => 'Sales', 'page_reference' => 'payment-programs', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'PP']),
        'statement_adjustments' => array_merge($filterReady, ['label' => 'Statement Adjustments', 'group' => 'Sales', 'page_reference' => 'statement-adjustments']),

        'supplier_payments' => array_merge($filterReady, ['label' => 'Supplier Payments', 'group' => 'Supplier / Purchase', 'page_reference' => 'supplier-payments', 'supports_branch_branding' => true, 'can_use_branch_branding' => true, 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'SP']),

        'productions' => array_merge($document, ['label' => 'Productions', 'group' => 'Production / Stock', 'page_reference' => 'productions', 'doc_identity_prefix' => 'P', 'safe_default_enabled' => true]),
        'physical_quantities' => array_merge($filterReady, ['label' => 'Physical Quantities / Stock', 'group' => 'Production / Stock', 'page_reference' => 'physical-quantities', 'supports_branch_branding' => true, 'can_use_branch_branding' => true]),

        'vouchers' => array_merge($document, ['label' => 'Vouchers', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'vouchers', 'doc_identity_prefix' => 'V', 'safe_default_enabled' => true]),
        'expenses' => array_merge($filterReady, ['label' => 'Expenses', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'expenses']),
        'daily_ledger' => array_merge($filterReady, ['label' => 'Daily Ledger', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'daily-ledger', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'DL']),
        'bank_accounts' => array_merge($filterReady, ['label' => 'Bank Accounts', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'bank-accounts', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'BA']),
        'utility_accounts' => array_merge($filterReady, ['label' => 'Utility Accounts', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'utility-accounts']),
        'utility_bills' => array_merge($filterReady, ['label' => 'Utility Bills', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'utility-bills', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'UB']),
        'employee_payments' => array_merge($filterReady, ['label' => 'Employee Payments', 'group' => 'Finance / Bank / Expenses', 'page_reference' => 'employee-payments']),

        'cargos' => array_merge($document, ['label' => 'Cargo', 'group' => 'Cargo / Documents', 'page_reference' => 'cargos', 'doc_identity_prefix' => 'C']),
        'bilties' => array_merge($filterReady, ['label' => 'Bilties', 'group' => 'Cargo / Documents', 'page_reference' => 'bilties', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'B']),
        'shipments' => array_merge($document, ['label' => 'Shipments', 'group' => 'Sales', 'page_reference' => 'shipments', 'doc_identity_prefix' => 'S']),
        'cr' => array_merge($filterReady, ['label' => 'CR', 'group' => 'Cargo / Documents', 'page_reference' => 'cr', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'CR']),
        'dr' => array_merge($filterReady, ['label' => 'DR', 'group' => 'Cargo / Documents', 'page_reference' => 'dr', 'supports_serial_prefix' => true, 'supports_branch_serial_prefix' => true, 'supports_doc_identity_prefix' => true, 'doc_identity_prefix' => 'DR']),

        'attendances' => array_merge($filterReady, ['label' => 'Attendances', 'group' => 'Attendance', 'page_reference' => 'attendances']),

        'reports' => array_merge($multiReport, ['label' => 'Reports', 'group' => 'Reports', 'page_reference' => 'reports']),
        'reports_article' => array_merge($multiReport, ['label' => 'Article Report', 'group' => 'Reports', 'page_reference' => 'reports/article']),
        'reports_statement' => array_merge($multiReport, ['label' => 'Statement Report', 'group' => 'Reports', 'page_reference' => 'reports/statement', 'supports_record_filtering' => true, 'can_filter_records' => true, 'has_branch_id_support' => true]),
        'reports_pending_payments' => array_merge($multiReport, ['label' => 'Pending Payments Report', 'group' => 'Reports', 'page_reference' => 'reports/pending-payments', 'supports_record_filtering' => true, 'can_filter_records' => true, 'has_branch_id_support' => true]),
        'reports_physical_quantity' => array_merge($multiReport, ['label' => 'Physical Quantity Report', 'group' => 'Reports', 'page_reference' => 'reports/physical-quantity', 'supports_record_filtering' => true, 'can_filter_records' => true, 'has_branch_id_support' => true]),
        'permissions_report' => array_merge($system, ['label' => 'Permissions Report', 'group' => 'Reports', 'page_reference' => 'permissions-report']),

        'developer_settings' => array_merge($system, ['label' => 'Developer Settings', 'group' => 'Developer / System', 'page_reference' => 'developer/settings']),
        'developer_branches' => array_merge($system, ['label' => 'Developer Branches', 'group' => 'Developer / System', 'page_reference' => 'developer/branches']),
        'developer_backups' => array_merge($system, ['label' => 'Developer Backups / Restore', 'group' => 'Developer / System', 'page_reference' => 'developer/backups']),
        'developer_license' => array_merge($system, ['label' => 'Developer License', 'group' => 'Developer / System', 'page_reference' => 'developer/license']),
        'developer_updater' => array_merge($system, ['label' => 'Developer Updater', 'group' => 'Developer / System', 'page_reference' => 'developer/updater']),
        'developer_audit_logs' => array_merge($system, ['label' => 'Developer Audit Logs', 'group' => 'Developer / System', 'page_reference' => 'developer/audit-logs']),
        'first_run_setup' => array_merge($system, ['label' => 'First Run Setup', 'group' => 'Developer / System', 'page_reference' => 'setup']),
        'subscription_expired' => array_merge($system, ['label' => 'Subscription Expired', 'group' => 'Developer / System', 'page_reference' => 'subscription-expired']),
        'updating' => array_merge($system, ['label' => 'Updating Screen', 'group' => 'Developer / System', 'page_reference' => 'updating']),
        'auth_login' => array_merge($system, ['label' => 'Login / Auth', 'group' => 'Developer / System', 'page_reference' => 'login']),
    ],
];
