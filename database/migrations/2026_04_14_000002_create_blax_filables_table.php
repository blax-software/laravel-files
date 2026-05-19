<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the `filables` pivot — the polymorphic attachment table that
 * links a {@see \Blax\Files\Models\File} to whatever host model owns it.
 *
 * Idempotent — same rationale as create_blax_files_table. The hybrid
 * auto-load + publishable pattern only works if re-running the migration
 * (after publish, or after someone partially created the schema by hand)
 * is a no-op rather than a crash.
 */
return new class extends Migration {
    public function up(): void
    {
        $name = config('files.table_names.filables', 'filables');

        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) {
            // UUID PK on the pivot so it pairs cleanly with the UUID-keyed
            // {@see \Blax\Files\Models\Filable} model (which uses HasUuids).
            $table->uuid('id')->primary();
            $table->uuid('file_id');
            // uuidMorphs for the same reason `user_id` is a UUID on the
            // files table: Blax host apps are UUID-PK by convention, so a
            // bigint morph FK wouldn't fit. Hosts that need bigint can
            // publish + edit.
            $table->uuidMorphs('filable');
            $table->string('as')->nullable()->index();
            $table->smallInteger('order')->nullable()->default(null);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('file_id')
                ->references('id')
                ->on(config('files.table_names.files', 'files'))
                ->cascadeOnDelete();

            $table->unique(['file_id', 'filable_type', 'filable_id', 'as'], 'filables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('files.table_names.filables', 'filables'));
    }
};
