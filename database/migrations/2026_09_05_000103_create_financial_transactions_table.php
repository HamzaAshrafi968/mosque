<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('person_type'); // student | teacher
            $table->uuid('person_id');
            $table->string('transaction_type'); // charge|payment|refund|transfer|adjustment
            $table->string('direction'); // money_in|money_out
            $table->decimal('amount', 12, 2);
            $table->string('related_person_type')->nullable(); // student | teacher
            $table->uuid('related_person_id')->nullable();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->foreignUuid('reverses_id')->nullable()->constrained('financial_transactions')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'person_type', 'person_id', 'created_at']);
            $table->index(['tenant_id', 'related_person_type', 'related_person_id']);
            $table->index(['tenant_id', 'reverses_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
