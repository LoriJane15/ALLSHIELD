<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATALOG_KEY = 'lgu-configurable-v1';

    private const PHASES = [
        0 => 'Pre-Shaping',
        1 => 'Shape',
        2 => 'Access',
        3 => 'Transform',
        4 => 'Sustain',
        5 => 'Monitor',
    ];

    private const ACTIVITY_TITLE_UNIQUE = 'rcsp_activity_barangay_phase_title_unique';

    private const FORM_VERSION_UNIQUE = 'rcsp_form_submission_version_unique';

    private const PHASE_STATUS_UNIQUE = 'rcsp_phase_status_barangay_unique';

    private const TRANSITION_UNIQUE = 'rcsp_phase_transition_from_unique';

    public function up(): void
    {
        $this->guardPreflight();

        Schema::table('rcsp_activities', function (Blueprint $table) {
            $table->foreignId('rcsp_barangay_id')->nullable()->after('rcsp_phase_id')
                ->constrained('rcsp_barangays')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('rcsp_barangay_id')
                ->constrained('users')->nullOnDelete();
            $table->string('normalized_title', 255)->nullable()->after('description');
            $table->unique(
                ['rcsp_barangay_id', 'rcsp_phase_id', 'normalized_title'],
                self::ACTIVITY_TITLE_UNIQUE
            );
        });

        Schema::table('rcsp_forms', function (Blueprint $table) {
            $table->unsignedInteger('submission_version')->nullable()->after('rcsp_activity_id');
            $table->string('original_filename')->nullable()->after('file');
            $table->string('detected_mime_type', 127)->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size_bytes')->nullable()->after('detected_mime_type');
            $table->timestamp('submitted_at')->nullable()->after('file_size_bytes');
        });

        $versions = [];
        $forms = DB::table('rcsp_forms')
            ->select(['id', 'rcsp_barangay_id', 'rcsp_phase_id', 'rcsp_activity_id', 'created_at'])
            ->orderBy('rcsp_barangay_id')->orderBy('rcsp_phase_id')
            ->orderBy('rcsp_activity_id')->orderBy('id')->get();
        foreach ($forms as $form) {
            $key = $form->rcsp_barangay_id.'|'.$form->rcsp_phase_id.'|'.$form->rcsp_activity_id;
            $version = ($versions[$key] ?? 0) + 1;
            $versions[$key] = $version;
            DB::table('rcsp_forms')->where('id', $form->id)->update([
                'submission_version' => $version,
                'submitted_at' => $form->created_at,
            ]);
        }

        Schema::table('rcsp_forms', function (Blueprint $table) {
            $table->unique(
                ['rcsp_barangay_id', 'rcsp_phase_id', 'rcsp_activity_id', 'submission_version'],
                self::FORM_VERSION_UNIQUE
            );
        });

        Schema::create('rcsp_form_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rcsp_form_id')->constrained('rcsp_forms')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->text('remarks')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('rcsp_phase_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rcsp_barangay_id')->constrained('rcsp_barangays')->cascadeOnDelete();
            $table->unsignedTinyInteger('from_phase');
            $table->unsignedTinyInteger('to_phase')->nullable();
            $table->foreignId('advanced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('advanced_at');
            $table->timestamps();
            $table->unique(['rcsp_barangay_id', 'from_phase'], self::TRANSITION_UNIQUE);
        });

        Schema::table('rcsp_phase_statuses', function (Blueprint $table) {
            $table->unique('rcsp_barangay_id', self::PHASE_STATUS_UNIQUE);
        });

        if (! DB::table('rcsp_phases')->where('catalog_key', self::CATALOG_KEY)->exists()) {
            $now = now();
            DB::table('rcsp_phases')->insert(collect(self::PHASES)->map(
                fn (string $name, int $number): array => [
                    'name' => $name,
                    'number' => $number,
                    'catalog_key' => self::CATALOG_KEY,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            )->values()->all());
        }
    }

    public function down(): void
    {
        $this->guardRollback();

        DB::table('rcsp_phases')->where('catalog_key', self::CATALOG_KEY)->delete();

        Schema::table('rcsp_phase_statuses', function (Blueprint $table) {
            $table->dropUnique(self::PHASE_STATUS_UNIQUE);
        });
        Schema::dropIfExists('rcsp_phase_transitions');
        Schema::dropIfExists('rcsp_form_reviews');

        Schema::table('rcsp_forms', function (Blueprint $table) {
            $table->dropUnique(self::FORM_VERSION_UNIQUE);
            $table->dropColumn([
                'submission_version', 'original_filename', 'detected_mime_type',
                'file_size_bytes', 'submitted_at',
            ]);
        });

        Schema::table('rcsp_activities', function (Blueprint $table) {
            $table->dropUnique(self::ACTIVITY_TITLE_UNIQUE);
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('rcsp_barangay_id');
            $table->dropColumn('normalized_title');
        });
    }

    private function guardPreflight(): void
    {
        if (Schema::hasTable('rcsp_form_reviews') || Schema::hasTable('rcsp_phase_transitions')
            || Schema::hasColumn('rcsp_activities', 'rcsp_barangay_id')
            || Schema::hasColumn('rcsp_activities', 'created_by_user_id')
            || Schema::hasColumn('rcsp_activities', 'normalized_title')
            || Schema::hasColumn('rcsp_forms', 'submission_version')
            || Schema::hasColumn('rcsp_forms', 'original_filename')
            || Schema::hasColumn('rcsp_forms', 'detected_mime_type')
            || Schema::hasColumn('rcsp_forms', 'file_size_bytes')
            || Schema::hasColumn('rcsp_forms', 'submitted_at')) {
            throw new RuntimeException(
                'Cannot add configurable RCSP activities: conflicting partial schema already exists. No schema or records were changed.'
            );
        }

        if (DB::table('rcsp_phase_statuses')->select('rcsp_barangay_id')
            ->groupBy('rcsp_barangay_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException(
                'Cannot add configurable RCSP activities: duplicate phase-status rows exist. No schema or records were changed.'
            );
        }

        $existing = DB::table('rcsp_phases')->where('catalog_key', self::CATALOG_KEY)
            ->orderBy('number')->get(['id', 'number', 'name']);
        if ($existing->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot create the configurable RCSP catalog: its catalog key already exists. No schema or records were changed.'
            );
        }
    }

    private function guardRollback(): void
    {
        if (DB::table('rcsp_barangays')->where('catalog_key', self::CATALOG_KEY)->exists()
            || DB::table('rcsp_activities')->whereNotNull('rcsp_barangay_id')->exists()
            || DB::table('rcsp_form_reviews')->exists()
            || DB::table('rcsp_phase_transitions')->exists()
            || DB::table('rcsp_forms')->where('submission_version', '>', 1)->exists()
            || DB::table('rcsp_forms')->whereNotNull('original_filename')->exists()
            || DB::table('rcsp_forms')->whereNotNull('detected_mime_type')->exists()
            || DB::table('rcsp_forms')->whereNotNull('file_size_bytes')->exists()) {
            throw new RuntimeException(
                'Cannot roll back configurable RCSP activities after the new workflow has been used. No schema or records were changed.'
            );
        }
    }
};
