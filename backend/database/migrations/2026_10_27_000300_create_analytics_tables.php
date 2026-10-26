<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw scan events. No IP addresses are stored; visitor_hash is a salted,
        // daily-rotating hash used only to estimate unique scans. Pruned by the scheduler.
        Schema::create('qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->timestamp('scanned_at');
            $table->char('country_code', 2)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('device_type', 16);
            $table->string('os', 32);
            $table->string('browser', 32);
            $table->string('referrer_host', 190)->nullable();
            $table->char('visitor_hash', 64);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_bot')->default(false);
            $table->index(['qr_code_id', 'scanned_at']);
            $table->index('scanned_at');
        });

        // Daily roll-ups that power every dashboard query.
        Schema::create('scan_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // total | device | os | browser | country | referrer | bot
            $table->string('dimension', 16);
            $table->string('value', 190)->default('');
            $table->unsignedInteger('scans')->default(0);
            $table->unsignedInteger('unique_scans')->default(0);
            $table->unique(['qr_code_id', 'date', 'dimension', 'value'], 'scan_agg_unique');
            $table->index(['user_id', 'dimension', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_aggregates');
        Schema::dropIfExists('qr_scans');
    }
};
