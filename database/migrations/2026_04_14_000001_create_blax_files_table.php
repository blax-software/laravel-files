<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the package's `files` table (canonical name overridable via
 * `config('files.table_names.files')`).
 *
 * Idempotent on purpose — see laravel-workkit/PRINCIPLES/laravel-composer-packages.md
 * (section "Migrations: hybrid auto-load + publishable"). The whole point of
 * the hybrid pattern is that a consumer can `composer require` + `php artisan
 * migrate` and have it work, OR publish the migrations to customise them
 * without the auto-loaded version re-creating the table underneath. Either
 * path must succeed even if the other already ran.
 */
return new class extends Migration {
    public function up(): void
    {
        $name = config('files.table_names.files', 'files');

        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) {
            $table->uuid('id')->primary();
            // UUID rather than `unsignedBigInteger` so the package works
            // out-of-the-box with the UUID-PK conventions used across the
            // Blax fleet (see PRINCIPLES section "UUIDs or ULIDs for
            // everything"). Hosts on bigint user IDs can publish the
            // migration and swap this column type before running it.
            $table->uuid('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('disk')->default(config('files.disk', 'local'));
            $table->string('relativepath')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('files.table_names.files', 'files'));
    }
};
