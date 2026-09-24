<?php

use App\Enums\Ib39FeaDocumentHistoryEvent;
use App\Enums\Ib39FeaOverallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->boolean('is_delayed')->default(false)->after('completed_at')->index();
            $table->text('compliance_reason')->nullable()->after('remarks');
        });

        Schema::create('ib39_fea_document_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_document_id')->constrained('ib39_fea_documents')->restrictOnDelete();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event', array_column(Ib39FeaDocumentHistoryEvent::cases(), 'value'));
            $table->text('previous_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamps();

            $table->index(['fea_document_id', 'created_at'], 'ib39_fea_document_history_created_index');
            $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_processing_document_history_index');
        });

        Schema::create('ib39_fea_processing_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('from_status', array_column(Ib39FeaOverallStatus::cases(), 'value'));
            $table->enum('to_status', array_column(Ib39FeaOverallStatus::cases(), 'value'));
            $table->string('event', 50);
            $table->timestamps();

            $table->index(['fea_processing_id', 'created_at'], 'ib39_fea_processing_history_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_fea_processing_histories');
        Schema::dropIfExists('ib39_fea_document_histories');

        Schema::table('ib39_fea_documents', function (Blueprint $table) {
            $table->dropIndex(['is_delayed']);
            $table->dropColumn(['is_delayed', 'compliance_reason']);
        });
    }
};
