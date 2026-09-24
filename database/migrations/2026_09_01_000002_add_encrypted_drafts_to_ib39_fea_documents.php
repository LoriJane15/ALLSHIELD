<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->text('draft_data')->nullable()->after('delay_reason');
            $table->unsignedSmallInteger('draft_schema_version')->nullable()->after('draft_data');
            $table->unsignedInteger('draft_revision')->default(0)->after('draft_schema_version');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_revision');
            $table->foreignId('draft_saved_by')->nullable()->after('draft_saved_at')->constrained('users')->restrictOnDelete();
        });

        Schema::create('ib39_fea_draft_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision');
            $table->json('changed_fields');
            $table->timestamps();
            $table->unique(['fea_document_id', 'revision']);
            $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_draft_history_processing_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_fea_draft_histories');
        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_saved_by');
            $table->dropColumn(['draft_data', 'draft_schema_version', 'draft_revision', 'draft_saved_at']);
        });
    }
};
