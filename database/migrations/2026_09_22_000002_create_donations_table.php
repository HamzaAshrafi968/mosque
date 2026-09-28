<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التبرعات والمساهمات: سجل مستقل لكل جامع بثلاثة أنواع
 * (مادي/عيني/معنوي) وحالة مراجعة (بانتظار/مقبول/مرفوض) — التقديم
 * من الموقع العام أو تسجيل يدوي من المدير، ولا يُربط بدفتر الشخصيات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('source')->default('public');
            $table->string('donor_name');
            $table->string('donor_phone')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
