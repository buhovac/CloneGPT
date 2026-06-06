<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('custom_about')->nullable()->after('preferred_model');
            $table->text('custom_behavior')->nullable()->after('custom_about');
            $table->text('custom_commands')->nullable()->after('custom_behavior');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['custom_about', 'custom_behavior', 'custom_commands']);
        });
    }
};
