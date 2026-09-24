<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasCompleteCurrentSchema()) {
            return;
        }

        DB::transaction(function (): void {
            $demoMunicipalityIds = DB::table('municipalities')
                ->whereRaw('LOWER(name) LIKE ?', ['%demo%'])->pluck('id');
            $demoAgencyIds = DB::table('gov_agencies')
                ->whereRaw('LOWER(name) LIKE ? OR LOWER(acronym) LIKE ?', ['%demo%', '%demo%'])->pluck('id');
            $demoBarangayIds = DB::table('barangays')
                ->whereIn('municipality_id', $demoMunicipalityIds)
                ->orWhereRaw('LOWER(name) LIKE ?', ['%demo%'])->pluck('id');

            $this->retainAccountsWithoutDemoAssignments($demoMunicipalityIds, $demoAgencyIds);
            $this->removeDemoChat();

            DB::table('implementations')
                ->whereRaw('LOWER(COALESCE(program, ?)) LIKE ?', ['', '%demo%'])->delete();

            $this->removeDemoRcsp($demoBarangayIds);

            DB::table('former_rebels')->whereIn('classified_id', [
                'FR-#9001', 'FR-#9002', 'FR-#9003',
            ])->delete();

            DB::table('map_barangays')->where(function ($query): void {
                $query->whereRaw('LOWER(COALESCE(fid, ?)) LIKE ?', ['', 'demo-%'])
                    ->orWhereRaw('LOWER(COALESCE(province, ?)) LIKE ?', ['', '%demo%'])
                    ->orWhereRaw('LOWER(COALESCE(municipality, ?)) LIKE ?', ['', '%demo%'])
                    ->orWhereRaw('LOWER(COALESCE(barangay, ?)) LIKE ?', ['', '%demo%']);
            })->delete();

            $this->removeDemoIb39Workflows();

            DB::table('barangays')->whereIn('id', $demoBarangayIds)->delete();
            DB::table('municipalities')->whereIn('id', $demoMunicipalityIds)->delete();
            DB::table('gov_agencies')->whereIn('id', $demoAgencyIds)->delete();
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: fabricated demo identities and workflows
        // must never be recreated by a production rollback.
    }

    private function hasCompleteCurrentSchema(): bool
    {
        $tables = [
            'municipalities', 'barangays', 'gov_agencies', 'users',
            'chat_conversations', 'chat_messages', 'chat_conversation_reads',
            'chat_message_document_references', 'implementations', 'former_rebels',
            'map_barangays', 'rcsp_phases', 'rcsp_barangays', 'rcsp_activities',
            'rcsp_forms', 'rcsp_file_comments', 'rcsp_lgu_comments', 'rcsp_form_reviews',
            'rcsp_phase_transitions', 'rcsp_phase_statuses', 'ib39_surfaced_former_rebels',
            'ib39_fr_cancellations', 'pswdo_enrollments', 'pswdo_enrollment_documents',
            'ib39_fea_processings', 'ib39_fea_documents', 'ib39_fea_document_versions',
            'ib39_fea_upload_histories', 'ib39_fea_draft_histories',
            'ib39_fea_document_histories', 'ib39_fea_processing_histories',
            'japic_certification_processings', 'japic_certification_document_versions',
            'japic_certification_photo_versions', 'ib39_cdr_processings', 'ib39_cdr_forms',
            'ib39_cdr_status_histories', 'ib39_cdr_document_versions', 'ib39_cdr_photos',
            'ib39_cdr_photo_versions',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return Schema::hasColumns('japic_certification_processings', [
            'current_final_version_id', 'current_photo_version_id',
        ]) && Schema::hasColumns('ib39_fea_documents', [
            'current_draft_version_id', 'current_surrendered_photo_version_id',
            'current_supporting_photo_version_id', 'current_final_version_id',
        ]) && Schema::hasColumn('gov_agencies', 'acronym_canonical');
    }

    private function retainAccountsWithoutDemoAssignments(Collection $municipalityIds, Collection $agencyIds): void
    {
        if ($municipalityIds->isNotEmpty() && DB::table('users')->whereIn('municipality_id', $municipalityIds)->exists()) {
            $municipalityId = DB::table('municipalities')->where('name', 'Digos City')->value('id');
            $municipalityId ??= DB::table('municipalities')->insertGetId([
                'name' => 'Digos City', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->whereIn('municipality_id', $municipalityIds)->update([
                'municipality_id' => $municipalityId, 'updated_at' => now(),
            ]);
        }

        if ($agencyIds->isNotEmpty() && DB::table('users')->whereIn('gov_agency_id', $agencyIds)->exists()) {
            $agencyId = DB::table('gov_agencies')->where('acronym_canonical', 'doh')->value('id');
            $agencyId ??= DB::table('gov_agencies')->insertGetId([
                'name' => 'Department of Health', 'acronym' => 'DOH', 'profile' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->whereIn('gov_agency_id', $agencyIds)->update([
                'gov_agency_id' => $agencyId, 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('users')->whereRaw('LOWER(name) LIKE ?', ['%demo%'])->get(['id', 'name']) as $user) {
            $name = trim((string) preg_replace('/\bdemo\b\s*/i', '', $user->name));
            DB::table('users')->where('id', $user->id)->update([
                'name' => $name !== '' ? $name : "System User {$user->id}",
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('users')->whereRaw('LOWER(username) LIKE ?', ['%demo%'])->get(['id', 'username']) as $user) {
            $base = trim((string) preg_replace('/[_-]*demo[_-]*/i', '_', $user->username), '_-');
            $base = $base !== '' ? $base : "system_user_{$user->id}";
            $username = $base;
            $suffix = 1;
            while (DB::table('users')->where('username', $username)->where('id', '<>', $user->id)->exists()) {
                $username = $base.'_'.++$suffix;
            }
            DB::table('users')->where('id', $user->id)->update([
                'username' => $username, 'updated_at' => now(),
            ]);
        }
    }

    private function removeDemoChat(): void
    {
        $messageIds = DB::table('chat_messages')
            ->whereRaw('LOWER(body) LIKE ?', ['%demo%'])->pluck('id');
        $conversationIds = DB::table('chat_messages')
            ->whereIn('id', $messageIds)->pluck('chat_conversation_id')->unique();

        DB::table('chat_message_document_references')->whereIn('chat_message_id', $messageIds)->delete();
        DB::table('chat_conversation_reads')->whereIn('chat_conversation_id', $conversationIds)->delete();
        DB::table('chat_messages')->whereIn('id', $messageIds)->delete();

        foreach ($conversationIds as $conversationId) {
            if (! DB::table('chat_messages')->where('chat_conversation_id', $conversationId)->exists()) {
                DB::table('chat_conversations')->where('id', $conversationId)->delete();
            }
        }
    }

    private function removeDemoRcsp(Collection $barangayIds): void
    {
        $phaseIds = DB::table('rcsp_phases')->where(function ($query): void {
            $query->whereRaw('LOWER(COALESCE(catalog_key, ?)) LIKE ?', ['', '%demo%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%demo%']);
        })->pluck('id');
        $rcspBarangayIds = DB::table('rcsp_barangays')->where(function ($query) use ($barangayIds): void {
            $query->whereIn('barangay_id', $barangayIds)
                ->orWhereRaw('LOWER(COALESCE(catalog_key, ?)) LIKE ?', ['', '%demo%']);
        })->pluck('id');
        $activityIds = DB::table('rcsp_activities')->where(function ($query) use ($phaseIds, $rcspBarangayIds): void {
            $query->whereIn('rcsp_phase_id', $phaseIds)
                ->orWhereIn('rcsp_barangay_id', $rcspBarangayIds)
                ->orWhereRaw('LOWER(description) LIKE ?', ['%demo%']);
        })->pluck('id');
        $formIds = DB::table('rcsp_forms')->where(function ($query) use ($phaseIds, $rcspBarangayIds, $activityIds): void {
            $query->whereIn('rcsp_phase_id', $phaseIds)
                ->orWhereIn('rcsp_barangay_id', $rcspBarangayIds)
                ->orWhereIn('rcsp_activity_id', $activityIds);
        })->pluck('id');

        DB::table('rcsp_file_comments')->where(function ($query) use ($formIds, $phaseIds, $activityIds): void {
            $query->whereIn('rcsp_form_id', $formIds)
                ->orWhereIn('rcsp_phase_id', $phaseIds)
                ->orWhereIn('rcsp_activity_id', $activityIds)
                ->orWhereRaw('LOWER(text) LIKE ?', ['%demo%']);
        })->delete();
        DB::table('rcsp_lgu_comments')->where(function ($query) use ($formIds, $phaseIds, $activityIds): void {
            $query->whereIn('rcsp_form_id', $formIds)
                ->orWhereIn('rcsp_phase_id', $phaseIds)
                ->orWhereIn('rcsp_activity_id', $activityIds);
        })->delete();
        DB::table('rcsp_form_reviews')->whereIn('rcsp_form_id', $formIds)->delete();
        DB::table('rcsp_forms')->whereIn('id', $formIds)->delete();
        DB::table('rcsp_phase_transitions')->whereIn('rcsp_barangay_id', $rcspBarangayIds)->delete();
        DB::table('rcsp_phase_statuses')->whereIn('rcsp_barangay_id', $rcspBarangayIds)->delete();
        DB::table('rcsp_activities')->whereIn('id', $activityIds)->delete();
        DB::table('rcsp_barangays')->whereIn('id', $rcspBarangayIds)->delete();
        DB::table('rcsp_phases')->whereIn('id', $phaseIds)->delete();
    }

    private function removeDemoIb39Workflows(): void
    {
        $surfacedIds = DB::table('ib39_surfaced_former_rebels')->where(function ($query): void {
            $query->whereRaw('LOWER(reference_number) LIKE ?', ['%demo%'])
                ->orWhereRaw('LOWER(COALESCE(province, ?)) LIKE ?', ['', '%demo%'])
                ->orWhereRaw('LOWER(COALESCE(initial_remarks, ?)) LIKE ?', ['', '%demo%'])
                ->orWhereRaw('LOWER(COALESCE(specific_location, ?)) LIKE ?', ['', '%demo%']);
        })->pluck('id');

        $this->removePswdo($surfacedIds);
        $this->removeFea($surfacedIds);
        $this->removeJapic($surfacedIds);
        $this->removeCdr($surfacedIds);

        DB::table('ib39_fr_cancellations')->whereIn('ib39_surfaced_former_rebel_id', $surfacedIds)->delete();
        DB::table('ib39_surfaced_former_rebels')->whereIn('id', $surfacedIds)->delete();
    }

    private function removePswdo(Collection $surfacedIds): void
    {
        $enrollmentIds = DB::table('pswdo_enrollments')
            ->whereIn('ib39_surfaced_former_rebel_id', $surfacedIds)->pluck('id');
        $documentIds = DB::table('pswdo_enrollment_documents')
            ->whereIn('pswdo_enrollment_id', $enrollmentIds)->pluck('id');
        DB::table('chat_message_document_references')
            ->whereIn('pswdo_enrollment_document_id', $documentIds)->delete();

        $postgres = DB::getDriverName() === 'pgsql';
        if ($postgres && $documentIds->isNotEmpty()) {
            DB::statement('DROP TRIGGER IF EXISTS pswdo_documents_no_delete ON pswdo_enrollment_documents');
        }

        try {
            DB::table('pswdo_enrollment_documents')->whereIn('id', $documentIds)->delete();
        } finally {
            if ($postgres && $documentIds->isNotEmpty()) {
                DB::statement('CREATE TRIGGER pswdo_documents_no_delete BEFORE DELETE ON pswdo_enrollment_documents FOR EACH ROW EXECUTE FUNCTION pswdo_document_immutable_guard()');
            }
        }

        DB::table('pswdo_enrollments')->whereIn('id', $enrollmentIds)->delete();
    }

    private function removeFea(Collection $surfacedIds): void
    {
        $processingIds = DB::table('ib39_fea_processings')
            ->whereIn('ib39_surfaced_former_rebel_id', $surfacedIds)->pluck('id');
        $documentIds = DB::table('ib39_fea_documents')
            ->whereIn('fea_processing_id', $processingIds)->pluck('id');
        $versionIds = DB::table('ib39_fea_document_versions')
            ->whereIn('fea_processing_id', $processingIds)->pluck('id');

        DB::table('ib39_fea_documents')->whereIn('id', $documentIds)->update([
            'current_draft_version_id' => null,
            'current_surrendered_photo_version_id' => null,
            'current_supporting_photo_version_id' => null,
            'current_final_version_id' => null,
        ]);
        DB::table('ib39_fea_document_versions')->whereIn('id', $versionIds)->update(['replaces_version_id' => null]);
        DB::table('chat_message_document_references')->whereIn('ib39_fea_document_version_id', $versionIds)->delete();
        DB::table('ib39_fea_upload_histories')->whereIn('fea_processing_id', $processingIds)->delete();
        DB::table('ib39_fea_draft_histories')->whereIn('fea_processing_id', $processingIds)->delete();
        DB::table('ib39_fea_document_histories')->whereIn('fea_processing_id', $processingIds)->delete();
        DB::table('ib39_fea_processing_histories')->whereIn('fea_processing_id', $processingIds)->delete();
        DB::table('ib39_fea_document_versions')->whereIn('id', $versionIds)->delete();
        DB::table('ib39_fea_documents')->whereIn('id', $documentIds)->delete();
        DB::table('ib39_fea_processings')->whereIn('id', $processingIds)->delete();
    }

    private function removeJapic(Collection $surfacedIds): void
    {
        $processingIds = DB::table('japic_certification_processings')
            ->whereIn('ib39_surfaced_former_rebel_id', $surfacedIds)->pluck('id');
        $documentIds = DB::table('japic_certification_document_versions')
            ->whereIn('processing_id', $processingIds)->pluck('id');

        DB::table('chat_message_document_references')
            ->whereIn('japic_certification_document_version_id', $documentIds)->delete();
        DB::table('japic_certification_processings')->whereIn('id', $processingIds)->update([
            'current_final_version_id' => null, 'current_photo_version_id' => null,
        ]);
        DB::table('japic_certification_photo_versions')->whereIn('processing_id', $processingIds)
            ->update(['replaces_version_id' => null]);
        DB::table('japic_certification_photo_versions')->whereIn('processing_id', $processingIds)->delete();
        DB::table('japic_certification_processings')->whereIn('id', $processingIds)->delete();
    }

    private function removeCdr(Collection $surfacedIds): void
    {
        $processingIds = DB::table('ib39_cdr_processings')
            ->whereIn('ib39_surfaced_former_rebel_id', $surfacedIds)->pluck('id');
        $documentIds = DB::table('ib39_cdr_document_versions')
            ->whereIn('cdr_processing_id', $processingIds)->pluck('id');
        $photoIds = DB::table('ib39_cdr_photos')
            ->whereIn('cdr_processing_id', $processingIds)->pluck('id');

        DB::table('chat_message_document_references')
            ->whereIn('ib39_cdr_document_version_id', $documentIds)->delete();
        DB::table('ib39_cdr_processings')->whereIn('id', $processingIds)
            ->update(['current_final_version_id' => null]);
        DB::table('ib39_cdr_photos')->whereIn('id', $photoIds)
            ->update(['current_photo_version_id' => null]);
        DB::table('ib39_cdr_document_versions')->whereIn('id', $documentIds)
            ->update(['replaces_version_id' => null]);
        DB::table('ib39_cdr_status_histories')->whereIn('cdr_processing_id', $processingIds)->delete();
        DB::table('ib39_cdr_forms')->whereIn('cdr_processing_id', $processingIds)->delete();
        DB::table('ib39_cdr_photo_versions')->whereIn('cdr_photo_id', $photoIds)->delete();
        DB::table('ib39_cdr_photos')->whereIn('id', $photoIds)->delete();
        DB::table('ib39_cdr_document_versions')->whereIn('id', $documentIds)->delete();
        DB::table('ib39_cdr_processings')->whereIn('id', $processingIds)->delete();
    }
};
