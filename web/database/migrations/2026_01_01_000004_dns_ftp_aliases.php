<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->timestamps();
        });

        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dns_zone_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name')->default('@');   // relative; '@' = zone apex
            $table->string('content');
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedInteger('prio')->default(0);
            $table->timestamps();
        });

        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->json('aliases')->nullable();   // extra ServerAlias domains
        });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn('aliases'));
        Schema::dropIfExists('ftp_accounts');
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
    }
};
