<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('physical_quantities', 'sales_return_id')) {
            Schema::table('physical_quantities', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_return_id')->nullable();
                $table->index('sales_return_id');
            });
        }

        $creatorId = DB::table('users')->value('id');

        DB::table('sales_returns')
            ->join('articles', 'articles.id', '=', 'sales_returns.article_id')
            ->leftJoin('physical_quantities', 'physical_quantities.sales_return_id', '=', 'sales_returns.id')
            ->whereNull('physical_quantities.id')
            ->select([
                'sales_returns.id as sales_return_id',
                'sales_returns.date',
                'sales_returns.article_id',
                'sales_returns.quantity',
                'articles.pcs_per_packet',
            ])
            ->orderBy('sales_returns.id')
            ->get()
            ->chunk(200)
            ->each(function ($salesReturns) use ($creatorId) {
                foreach ($salesReturns as $salesReturn) {
                    $pcsPerPacket = (float) ($salesReturn->pcs_per_packet ?? 0);

                    if ($pcsPerPacket <= 0) {
                        continue;
                    }

                    $packets = (float) $salesReturn->quantity / $pcsPerPacket;

                    $legacyPhysicalQuantity = DB::table('physical_quantities')
                        ->whereNull('sales_return_id')
                        ->where('category', 'sales_return')
                        ->where('article_id', $salesReturn->article_id)
                        ->whereDate('date', $salesReturn->date)
                        ->whereRaw('ABS(packets - ?) < 0.0001', [$packets])
                        ->orderBy('id')
                        ->first();

                    if ($legacyPhysicalQuantity) {
                        DB::table('physical_quantities')
                            ->where('id', $legacyPhysicalQuantity->id)
                            ->update([
                                'sales_return_id' => $salesReturn->sales_return_id,
                                'updated_at' => now(),
                            ]);

                        continue;
                    }

                    if (!$creatorId) {
                        continue;
                    }

                    DB::table('physical_quantities')->insert([
                        'date' => $salesReturn->date,
                        'article_id' => $salesReturn->article_id,
                        'packets' => $packets,
                        'category' => 'sales_return',
                        'sales_return_id' => $salesReturn->sales_return_id,
                        'creator_id' => $creatorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('physical_quantities', 'sales_return_id')) {
            Schema::table('physical_quantities', function (Blueprint $table) {
                $table->dropIndex(['sales_return_id']);
                $table->dropColumn('sales_return_id');
            });
        }
    }
};
