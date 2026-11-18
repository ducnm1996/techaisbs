<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('pregnancy_start')->nullable();
        });
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('meal_type', 20);
            $table->string('title', 160);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'entry_date']);
        });
        Schema::create('checkups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('title', 160);
            $table->string('clinic', 160)->nullable();
            $table->text('notes')->nullable();
            $table->date('next_visit')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkups');
        Schema::dropIfExists('meals');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('pregnancy_start'));
    }
};
