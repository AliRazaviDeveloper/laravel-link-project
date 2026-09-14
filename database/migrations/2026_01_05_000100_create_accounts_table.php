<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            // ULID rather than a bigint: ids appear in API responses and log lines,
            // and a sequential integer there tells everyone how many accounts exist.
            $table->char('id', 26)->primary();
            $table->string('email', 254)->unique();
            $table->string('name', 120);
            $table->string('plan', 16)->default('free');
            $table->string('password');
            $table->string('remember_token', 100)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
