<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('account_id', 26);

            // The unique index here is the real arbiter of slug ownership. The
            // application checks first for a tidy error message, but only this
            // constraint holds under two simultaneous creates.
            $table->string('slug', 48)->unique();

            $table->string('destination_url', 2048);

            // Denormalised from destination_url so "all links pointing at
            // example.com" is an indexed lookup rather than a LIKE over full URLs.
            $table->string('destination_host', 253)->index();

            $table->string('title', 160)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();

            // Reconciled from Redis by shortwave:flush-clicks, so it trails live
            // traffic by up to one scheduler tick.
            $table->unsignedBigInteger('click_count')->default(0);

            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();

            // Serves the default list page: one account's links, newest first,
            // optionally filtered by status.
            $table->index(['account_id', 'status', 'created_at'], 'links_account_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
