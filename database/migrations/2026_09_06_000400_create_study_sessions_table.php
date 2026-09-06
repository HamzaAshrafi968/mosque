<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // الدوام الافتراضي لكل جامع موجود: الأول والثاني (مثل الدورة الصباحية والمسائية).
        $now = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (['الدوام الأول', 'الدوام الثاني'] as $index => $name) {
                DB::table('study_sessions')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
