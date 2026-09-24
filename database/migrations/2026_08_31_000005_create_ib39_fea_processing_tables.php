<?php

use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FeaDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_fea_processings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ib39_surfaced_former_rebel_id')
                ->unique()
                ->constrained('ib39_surfaced_former_rebels')
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ib39_fea_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fea_processing_id')->constrained('ib39_fea_processings')->restrictOnDelete();
            $table->enum('document_type', array_column(Ib39FeaDocumentType::cases(), 'value'));
            $table->enum('status', array_column(Ib39FeaDocumentStatus::cases(), 'value'))->default(Ib39FeaDocumentStatus::Pending->value)->index();
            $table->enum('compliance_status', array_column(Ib39FeaComplianceStatus::cases(), 'value'))->default(Ib39FeaComplianceStatus::None->value)->index();
            $table->boolean('is_required')->default(true)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('delay_reason')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fea_processing_id', 'document_type'], 'ib39_fea_processing_document_unique');
        });

        $this->backfill();
    }

    private function backfill(): void
    {
        $now = now();

        DB::table('ib39_surfaced_former_rebels')
            ->where('possessed_firearms', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $surfacedFormerRebelId) use ($now): void {
                DB::table('ib39_fea_processings')->insertOrIgnore([
                    'ib39_surfaced_former_rebel_id' => $surfacedFormerRebelId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $processingId = DB::table('ib39_fea_processings')
                    ->where('ib39_surfaced_former_rebel_id', $surfacedFormerRebelId)
                    ->value('id');

                foreach (Ib39FeaDocumentType::cases() as $type) {
                    DB::table('ib39_fea_documents')->insertOrIgnore([
                        'fea_processing_id' => $processingId,
                        'document_type' => $type->value,
                        'status' => Ib39FeaDocumentStatus::Pending->value,
                        'compliance_status' => Ib39FeaComplianceStatus::None->value,
                        'is_required' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_fea_documents');
        Schema::dropIfExists('ib39_fea_processings');
    }
};
