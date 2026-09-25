<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// IMPLAN (Implementation Plan) lifecycle: LGU creates, agencies accept/reject,
// admin verifies/reassigns.
// Sources: fr_rscp_implementation, fr_rscp_implan_file, fr_rscp_implan_photo,
// agency_implan_responses, fr_rscp_tagging.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('implementations', function (Blueprint $table) {
            $table->id();                        // was fr_rcsp_imp_id
            $table->foreignId('lgu_user_id')->constrained('users')->cascadeOnDelete(); // was fr_lgu_id
            $table->date('uploaded_at')->nullable();
            $table->text('issues')->nullable();
            $table->text('program')->nullable();
            // Normalized from CSV-in-text; cast to array in the model.
            $table->json('target_areas')->nullable();   // was fr_rcsp_imp_area (csv barangay ids)
            $table->json('agencies')->nullable();        // was fr_rcsp_imp_agency (csv agency ids)
            $table->text('beneficiaries')->nullable();
            $table->text('outcome')->nullable();
            $table->text('resources')->nullable();
            $table->text('support')->nullable();
            $table->text('duration')->nullable();
            $table->enum('status', ['not yet started', 'submitted', 'ongoing', 'verified', 'for verification'])
                ->default('not yet started');
            $table->enum('type_gov', ['NGA', 'PGO', 'Development Partner'])->nullable();
            $table->string('sources')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('tagging', ['Accepted', 'Rejected'])->nullable();
            $table->timestamps();
        });

        Schema::create('implementation_files', function (Blueprint $table) {
            $table->id();                        // was fr_rscp_implan_file_id
            $table->foreignId('implementation_id')->constrained('implementations')->cascadeOnDelete();
            $table->string('file_name');
            $table->text('description')->nullable();
            $table->string('pdf');
            $table->timestamps();
        });

        Schema::create('implementation_photos', function (Blueprint $table) {
            $table->id();                        // was fr_rcsp_photo_id
            $table->foreignId('implementation_id')->constrained('implementations')->cascadeOnDelete();
            $table->string('image');
            $table->timestamps();
        });

        Schema::create('agency_implan_responses', function (Blueprint $table) {
            $table->id();                        // was response_id
            $table->foreignId('gov_agency_id')->constrained('gov_agencies')->cascadeOnDelete(); // was agency_id
            $table->foreignId('implementation_id')->constrained('implementations')->cascadeOnDelete(); // was implan_id
            $table->enum('response_status', ['accepted', 'rejected', 'pending'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->unique(['gov_agency_id', 'implementation_id']);
        });

        Schema::create('implementation_taggings', function (Blueprint $table) {
            $table->id();                        // was fr_rscp_tag_id
            $table->foreignId('implementation_id')->constrained('implementations')->cascadeOnDelete();
            $table->foreignId('gov_agency_id')->constrained('gov_agencies')->cascadeOnDelete();
            $table->enum('status', ['Pending', 'Accepted', 'Rejected', 'Reassigned'])->default('Pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_taggings');
        Schema::dropIfExists('agency_implan_responses');
        Schema::dropIfExists('implementation_photos');
        Schema::dropIfExists('implementation_files');
        Schema::dropIfExists('implementations');
    }
};
