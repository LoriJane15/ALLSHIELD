<?php

use App\Enums\Ib39FeaUploadSlot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_fea_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->enum('slot', array_column(Ib39FeaUploadSlot::cases(), 'value'));
            $table->unsignedInteger('version_number');
            $table->foreignId('replaces_version_id')->nullable()->constrained('ib39_fea_document_versions')->restrictOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->string('storage_path', 500);
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fea_document_id', 'slot', 'version_number'], 'ib39_fea_version_number_unique');
            $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_version_processing_index');
        });

        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->foreignId('current_draft_version_id')->nullable()->after('draft_saved_by')->constrained('ib39_fea_document_versions')->restrictOnDelete();
            $table->foreignId('current_supporting_photo_version_id')->nullable()->after('current_draft_version_id')->constrained('ib39_fea_document_versions')->restrictOnDelete();
        });

        Schema::create('ib39_fea_upload_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->foreignId('fea_document_version_id')->constrained('ib39_fea_document_versions')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->enum('slot', array_column(Ib39FeaUploadSlot::cases(), 'value'));
            $table->unsignedInteger('version_number');
            $table->timestamps();
            $table->index(['fea_document_id', 'created_at'], 'ib39_fea_upload_history_document_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_fea_upload_histories');
        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_supporting_photo_version_id');
            $table->dropConstrainedForeignId('current_draft_version_id');
        });
        Schema::dropIfExists('ib39_fea_document_versions');
    }
};
