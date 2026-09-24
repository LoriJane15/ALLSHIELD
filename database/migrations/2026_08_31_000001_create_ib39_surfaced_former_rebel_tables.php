<?php

use App\Enums\Ib39ForwardingOrganization;
use App\Enums\Ib39FrCategory;
use App\Models\Ib39ReferenceSequence;
use App\Models\Ib39SurfacedFormerRebel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ib39_reference_sequences', function (Blueprint $table) {
            $table->string('sequence_name')->primary();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        DB::table('ib39_reference_sequences')->insert([
            'sequence_name' => Ib39ReferenceSequence::SURFACED_FORMER_REBELS,
            'current_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('ib39_surfaced_former_rebels', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->enum('category', array_column(Ib39FrCategory::cases(), 'value'))->index();
            $table->string('other_category_specification')->nullable();
            $table->boolean('forwarded')->index();
            $table->enum('forwarding_organization', array_column(Ib39ForwardingOrganization::cases(), 'value'))->nullable()->index();
            $table->string('other_organization_specification')->nullable();
            $table->string('province', 100)->default(Ib39SurfacedFormerRebel::DEFAULT_PROVINCE);
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('specific_location')->nullable();
            $table->date('surfaced_at')->index();
            $table->boolean('possessed_firearms')->index();
            $table->text('initial_remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipality_id', 'surfaced_at'], 'ib39_surfaced_fr_municipality_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ib39_surfaced_former_rebels');
        Schema::dropIfExists('ib39_reference_sequences');
    }
};
