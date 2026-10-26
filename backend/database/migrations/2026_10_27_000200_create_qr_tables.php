<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('color', 7)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('type', 20);
            $table->string('mode', 10);
            $table->string('status', 10)->default('active');
            // Case-sensitive short code; binary collation keeps "aB3" and "Ab3" distinct on MySQL.
            $shortCode = $table->string('short_code', 16)->nullable()->unique();
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $shortCode->collation('utf8mb4_bin');
            }
            // Type-specific content. Encrypted at rest because it may hold Wi-Fi passwords or contact data.
            $table->text('content');
            $table->text('static_payload')->nullable();
            $table->unsignedBigInteger('total_scans')->default(0);
            $table->unsignedBigInteger('unique_scans')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('moderation_status', 20)->default('clear');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'mode']);
        });

        Schema::create('qr_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            // primary | ios | android | fallback | link | social
            $table->string('kind', 20);
            $table->string('platform', 30)->nullable();
            $table->string('label', 120)->nullable();
            $table->string('url', 2048);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['qr_code_id', 'kind', 'position']);
        });

        Schema::create('qr_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20);
            $table->string('disk', 32);
            $table->string('path');
            $table->string('public_token', 40)->unique();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'purpose']);
        });

        Schema::create('qr_designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('config');
            $table->foreignId('logo_file_id')->nullable()->constrained('qr_files')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('qr_destination_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary', 255);
            $table->timestamp('created_at');
            $table->index(['qr_code_id', 'created_at']);
        });

        Schema::create('abuse_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained()->cascadeOnDelete();
            $table->string('reporter_email')->nullable();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abuse_reports');
        Schema::dropIfExists('qr_destination_changes');
        Schema::dropIfExists('qr_designs');
        Schema::dropIfExists('qr_files');
        Schema::dropIfExists('qr_destinations');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('folders');
    }
};
