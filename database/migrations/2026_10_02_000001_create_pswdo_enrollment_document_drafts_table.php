<?php

use App\Enums\PswdoEnrollmentDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pswdo_enrollment_document_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pswdo_enrollment_id')->constrained('pswdo_enrollments')->restrictOnDelete();
            $table->enum('document_type', array_column(PswdoEnrollmentDocumentType::cases(), 'value'));
            $table->longText('payload');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('saved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['pswdo_enrollment_id', 'document_type'], 'pswdo_enrollment_document_draft_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pswdo_enrollment_document_drafts')
            && DB::table('pswdo_enrollment_document_drafts')->exists()) {
            throw new RuntimeException('PSWDO document draft rollback refused: saved drafts would be lost.');
        }

        Schema::dropIfExists('pswdo_enrollment_document_drafts');
    }
};
