<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('php_version');
            $table->string('doc_root');
            $table->string('system_user');
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('cf_only')->default(false);
            $table->json('ini')->nullable();
            $table->timestamps();
        });

        Schema::create('site_databases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('db_user');
            $table->timestamps();
        });

        Schema::create('mail_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('dkim_selector')->default('mail');
            $table->text('dkim_dns')->nullable();
            $table->timestamps();
        });

        Schema::create('mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_domain_id')->constrained()->cascadeOnDelete();
            $table->string('address')->unique();
            $table->timestamps();
        });

        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('schedule');
            $table->string('command', 500);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('details')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('cron_jobs');
        Schema::dropIfExists('mailboxes');
        Schema::dropIfExists('mail_domains');
        Schema::dropIfExists('site_databases');
        Schema::dropIfExists('sites');
    }
};
