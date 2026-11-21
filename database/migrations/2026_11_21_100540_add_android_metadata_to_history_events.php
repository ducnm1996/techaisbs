<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_events', function (Blueprint $table): void {
            $table->text('app_package')->nullable();
            $table->text('call_direction')->nullable();
            $table->text('duration_seconds')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('history_events', fn (Blueprint $table) => $table->dropColumn(['app_package', 'call_direction', 'duration_seconds']));
    }
};
