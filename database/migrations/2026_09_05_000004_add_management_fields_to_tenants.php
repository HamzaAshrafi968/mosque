<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('status')->default('active')->after('is_active');
            $table->string('logo')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'email', 'status', 'logo']);
        });
    }
};
