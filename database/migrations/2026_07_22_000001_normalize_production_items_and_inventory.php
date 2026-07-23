<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('material');
            $table->string('unit')->nullable();
            $table->string('tag')->nullable();
            $table->foreignId('fabric_id')->nullable()->constrained('setups')->nullOnDelete();
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'type']);
            $table->index(['tag']);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('direction');
            $table->date('date')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('payment_method')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->nullableMorphs('source');
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'direction']);
        });

        Schema::create('production_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('tag');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit')->nullable();
            $table->foreignId('worker_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('fabric_id')->nullable()->constrained('fabrics')->nullOnDelete();
            $table->timestamps();

            $table->index(['tag', 'worker_id']);
        });

        Schema::create('production_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained('productions')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('title');
            $table->string('unit')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'inventory_item_id']);
        });

        DB::table('productions')
            ->select(['id', 'branch_id', 'worker_id', 'tags', 'materials'])
            ->orderBy('id')
            ->chunkById(100, function ($productions) {
                foreach ($productions as $production) {
                    $tags = json_decode($production->tags ?? '[]', true);
                    if (is_array($tags)) {
                        foreach ($tags as $tag) {
                            $tag = (array) $tag;
                            if (empty($tag['tag']) || empty($tag['quantity'])) {
                                continue;
                            }

                            DB::table('production_tags')->insert([
                                'production_id' => $production->id,
                                'branch_id' => $production->branch_id ?? null,
                                'tag' => (string) $tag['tag'],
                                'quantity' => (float) $tag['quantity'],
                                'unit' => $tag['unit'] ?? null,
                                'worker_id' => $production->worker_id ?? null,
                                'fabric_id' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $materials = json_decode($production->materials ?? '[]', true);
                    if (is_array($materials)) {
                        foreach ($materials as $material) {
                            $material = (array) $material;
                            $title = trim((string) ($material['title'] ?? $material['name'] ?? ''));
                            if ($title === '' || empty($material['quantity'])) {
                                continue;
                            }

                            DB::table('production_materials')->insert([
                                'production_id' => $production->id,
                                'branch_id' => $production->branch_id ?? null,
                                'inventory_item_id' => null,
                                'title' => $title,
                                'unit' => $material['unit'] ?? null,
                                'quantity' => (float) $material['quantity'],
                                'unit_price' => isset($material['unit_price']) ? (float) $material['unit_price'] : null,
                                'amount' => isset($material['amount']) ? (float) $material['amount'] : null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
        Schema::dropIfExists('production_tags');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_items');
    }
};
