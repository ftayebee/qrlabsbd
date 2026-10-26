<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('changes')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('group', 40)->index();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->string('subject', 200);
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            // legal | landing
            $table->string('type', 20)->index();
            $table->string('title', 200);
            $table->longText('body');
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 255);
            $table->text('answer');
            $table->string('category', 60)->nullable()->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('topic', 40);
            $table->text('message');
            $table->string('status', 16)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_messages', 'faqs', 'content_pages', 'email_templates', 'system_settings', 'audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
