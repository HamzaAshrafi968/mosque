<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('tenant_id')->exists()) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->nullable(false)->change();
        });
    }
};
