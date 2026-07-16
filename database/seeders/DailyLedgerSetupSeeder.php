<?php

namespace Database\Seeders;

use App\Models\Setup;
use Illuminate\Database\Seeder;

class DailyLedgerSetupSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            Setup::updateOrCreate(
                [
                    'type' => $item['type'],
                    'title' => $item['title'],
                ],
                [
                    'short_title' => $item['short_title'],
                ]
            );
        }
    }

    public static function items(): array
    {
        return [
            ['type' => 'daily_ledger_method', 'title' => 'Cash', 'short_title' => 'DLM-CASH'],
            ['type' => 'daily_ledger_method', 'title' => 'Cheque', 'short_title' => 'DLM-CHEQUE'],
            ['type' => 'daily_ledger_method', 'title' => 'Less Copy A/c', 'short_title' => 'DLM-LESS-COPY'],
            ['type' => 'daily_ledger_method', 'title' => 'Online', 'short_title' => 'DLM-ONLINE'],
            ['type' => 'daily_ledger_method', 'title' => 'Adjustment', 'short_title' => 'DLM-ADJUSTMENT'],

            ['type' => 'daily_ledger_case', 'title' => 'Add Copy A/c', 'short_title' => 'DLC-ADD-COPY'],
            ['type' => 'daily_ledger_case', 'title' => 'Big Box (Bara Dbba)', 'short_title' => 'DLC-BIG-BOX'],
            ['type' => 'daily_ledger_case', 'title' => 'Zakat', 'short_title' => 'DLC-ZAKAT'],
            ['type' => 'daily_ledger_case', 'title' => 'Sadqa', 'short_title' => 'DLC-SADQA'],
            ['type' => 'daily_ledger_case', 'title' => 'Charity', 'short_title' => 'DLC-CHARITY'],
            ['type' => 'daily_ledger_case', 'title' => 'Daily Expenses', 'short_title' => 'DLC-DAILY-EXP'],
            ['type' => 'daily_ledger_case', 'title' => 'Exp 25# Plot', 'short_title' => 'DLC-25-PLOT'],
            ['type' => 'daily_ledger_case', 'title' => 'Home Expenses', 'short_title' => 'DLC-HOME-EXP'],
            ['type' => 'daily_ledger_case', 'title' => 'Personal Zubair bhai', 'short_title' => 'DLC-PER-ZUBAIR'],
            ['type' => 'daily_ledger_case', 'title' => 'Personal Ali bhai', 'short_title' => 'DLC-PER-ALI'],
            ['type' => 'daily_ledger_case', 'title' => 'Personal Abdullah', 'short_title' => 'DLC-PER-ABDULLAH'],
            ['type' => 'daily_ledger_case', 'title' => 'Personal Basit', 'short_title' => 'DLC-PER-BASIT'],
            ['type' => 'daily_ledger_case', 'title' => 'Rent Zubair Bhai', 'short_title' => 'DLC-RENT-ZUBAIR'],
            ['type' => 'daily_ledger_case', 'title' => 'Rent Ali Bhai', 'short_title' => 'DLC-RENT-ALI'],
            ['type' => 'daily_ledger_case', 'title' => 'Return Amount', 'short_title' => 'DLC-RETURN'],
            ['type' => 'daily_ledger_case', 'title' => 'Staff Salary', 'short_title' => 'DLC-STAFF-SALARY'],
            ['type' => 'daily_ledger_case', 'title' => 'Utility Bill', 'short_title' => 'DLC-UTILITY'],
            ['type' => 'daily_ledger_case', 'title' => 'Weekly Payment', 'short_title' => 'DLC-WEEKLY-PAY'],
            ['type' => 'daily_ledger_case', 'title' => 'Adjustment', 'short_title' => 'DLC-ADJUSTMENT'],
        ];
    }
}
