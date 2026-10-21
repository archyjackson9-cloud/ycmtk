<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // Nullable + unique: NULL (SMS rows, manual resends) never
            // collides; email rows carry a key so a repeated callback can't
            // queue the same email twice.
            $table->string('dedupe_key', 120)->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropUnique(['dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
    }
};
