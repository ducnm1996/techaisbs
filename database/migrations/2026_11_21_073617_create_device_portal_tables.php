<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('member');
            $table->boolean('is_active')->default(true);
            $table->boolean('setup_pending')->default(true);
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedBigInteger('mfa_last_step')->nullable();
        });
        Schema::create('devices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('sources');
            $table->boolean('allow_ip')->default(false);
            $table->boolean('allow_location')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('pairing_codes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->json('sources');
            $table->boolean('allow_ip')->default(false);
            $table->boolean('allow_location')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('history_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('event_id');
            $table->string('source', 16);
            $table->timestamp('occurred_at');
            foreach (['sender_phone', 'sender_name', 'server_domain', 'device_ip', 'latitude', 'longitude', 'accuracy_m'] as $column) {
                $table->text($column)->nullable();
            }
            $table->char('phone_index', 64)->nullable();
            $table->timestamps();
            $table->unique(['device_id', 'event_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'phone_index']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->json('changes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'history_events', 'personal_access_tokens', 'pairing_codes', 'devices'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active', 'setup_pending', 'mfa_secret', 'mfa_confirmed_at', 'mfa_last_step']));
    }
};
