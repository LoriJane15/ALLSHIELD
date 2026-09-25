<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off migration of the raw-PHP `kp_datacenter` database into the clean
 * `shield_db` schema. Reads via the `legacy` connection, writes via default.
 *
 * Original IDs are preserved wherever a foreign key depends on them so the
 * whole graph stays consistent. Table/column name inconsistencies and the two
 * different `fr_lgu_id` conventions are reconciled here.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'import:legacy {--fresh : Wipe destination tables first}';
    protected $description = 'Import and transform legacy kp_datacenter data into shield_db';

    private $legacy;

    public function handle(): int
    {
        $this->legacy = DB::connection('legacy');

        try {
            $this->legacy->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot reach legacy DB: '.$e->getMessage());
            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        // Preserve literal id 0 (the Pre-Shaping phase) instead of auto-generating.
        DB::statement("SET SESSION sql_mode=(SELECT CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO'))");

        if ($this->option('fresh')) {
            $this->wipe();
        }

        // dilg_lgu row-id => kp_user_id  (implementations store the row id)
        $lguRowToUser = $this->legacy->table('fr_dilg_lgu')->pluck('kp_user_id', 'fr_lgu_id');
        // kp_gov_agency_id => fr_gov_agency_id  (responses/taggings store the link id)
        $kpAgencyToAgency = $this->legacy->table('kp_gov_agency')->pluck('fr_gov_agency_id', 'kp_gov_agency_id');

        $this->importUsers();
        $this->importLookups();
        $this->importFormerRebels();
        $this->importMap();
        $this->importRcsp();
        $this->importImplementations($lguRowToUser, $kpAgencyToAgency);

        Schema::enableForeignKeyConstraints();

        $this->newLine();
        $this->info('Legacy import complete.');
        return self::SUCCESS;
    }

    private function wipe(): void
    {
        foreach ([
            'implementation_taggings', 'agency_implan_responses', 'implementation_photos',
            'implementation_files', 'implementations', 'implementation_plans', 'rcsp_lgu_comments', 'rcsp_file_comments',
            'rcsp_forms', 'rcsp_phase_statuses', 'rcsp_barangays', 'rcsp_activities', 'rcsp_phases',
            'color_histories', 'map_barangays', 'fr_government_assistances', 'fr_skills',
            'fr_location_histories', 'fr_education_works', 'fr_program_statuses', 'former_rebels',
            'barangays', 'gov_agencies', 'municipalities', 'users',
        ] as $t) {
            DB::table($t)->truncate();
        }
        $this->line('Destination wiped.');
    }

    private function nn($v)   // normalize empty '' and zero-dates => null
    {
        if ($v === '' || $v === null) {
            return null;
        }
        if (is_string($v) && str_starts_with($v, '0000-00-00')) {
            return null;
        }
        return $v;
    }

    // -- Users (kp_user + per-role fullname/link tables) ------------------
    private function importUsers(): void
    {
        // full name lives in a different table per role
        $names = [];
        foreach ([
            'super_admin' => ['kp_super_admin', 'kp_super_admin_fullname'],
            'admin'       => ['kp_admin', 'kp_admin_fullname'],
            '39th_ib'     => ['kp_camp', 'kp_camp_fullname'],
            'gov_agency'  => ['kp_gov_agency', 'kp_gov_agency_fullname'],
            'mblrc'       => ['kp_mblrc', 'kp_mblrc_fullname'],
            'afp'         => ['kp_afp', 'kp_afp_fullname'],
        ] as [$tbl, $col]) {
            foreach ($this->legacy->table($tbl)->get() as $r) {
                $names[$r->kp_user_id] = $r->$col;
            }
        }
        foreach ($this->legacy->table('fr_dilg_lgu')->get() as $r) {
            $names[$r->kp_user_id] = $r->fr_lgu_fullname;
        }
        // role-scoped foreign keys
        $muniByUser = $this->legacy->table('fr_dilg_lgu')->pluck('fr_municipal_id', 'kp_user_id');
        $agencyByUser = $this->legacy->table('kp_gov_agency')->pluck('fr_gov_agency_id', 'kp_user_id');

        $rows = [];
        foreach ($this->legacy->table('kp_user')->get() as $u) {
            $rows[] = [
                'id' => $u->kp_user_id,
                'username' => $u->kp_user_username,
                'name' => $names[$u->kp_user_id] ?? $u->kp_user_username,
                'email' => null,
                'password' => $u->kp_user_password,   // already bcrypt-hashed; keep as-is
                'role' => $u->kp_user_role,
                'logo' => $this->nn($u->kp_user_logo),
                'municipality_id' => $muniByUser[$u->kp_user_id] ?? null,
                'gov_agency_id' => $agencyByUser[$u->kp_user_id] ?? null,
                'created_at' => $u->kp_user_datetime,
                'updated_at' => $u->kp_user_datetime,
            ];
        }
        $this->insertChunked('users', $rows);
    }

    private function importLookups(): void
    {
        $this->insertChunked('municipalities', $this->legacy->table('fr_municipal')->get()
            ->map(fn ($r) => ['id' => $r->fr_municipal_id, 'name' => $r->fr_municipal_name])->all());

        $this->insertChunked('barangays', $this->legacy->table('fr_barangay')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_barangay_id,
                'municipality_id' => $r->fr_municipal_id,
                'name' => $r->fr_barangay_name,
            ])->all());

        $this->insertChunked('gov_agencies', $this->legacy->table('fr_gov_agency')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_gov_agency_id,
                'name' => $r->fr_gov_agency_name,
                'acronym' => $r->fr_gov_agency_acro,
                'profile' => $this->nn($r->fr_gov_agency_prof),
            ])->all());
    }

    private function importFormerRebels(): void
    {
        $this->insertChunked('former_rebels', $this->legacy->table('fr_registered')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_regis_id,
                'classified_id' => $r->fr_classified_id,
                'firstname' => $r->fr_firstname,
                'lastname' => $r->fr_lastname,
                'middlename' => $this->nn($r->fr_mname),
                'nickname' => $this->nn($r->fr_nickname),
                'suffix' => $this->nn($r->fr_suffix),
                'gender' => $this->nn($r->fr_gender),
                'age' => $r->fr_age ?: null,
                'civil_status' => $this->nn($r->fr_civil_status),
                'residential_address' => $this->nn($r->fr_residential_address),
                'placement_address' => $this->nn($r->fr_placement_address),
                'birthdate' => $this->nn($r->fr_bday),
                'contact_num' => $this->nn($r->fr_contact_num),
                'batch_year' => $this->nn($r->fr_batch_year),
                'batch_section' => $this->nn($r->fr_batch_sec),
                'zipcode' => $this->nn($r->fr_regis_zipcode),
                'barangay_id' => $r->fr_regis_barangay ?: null,
                'municipality_id' => $r->fr_regis_municipal ?: null,
                'province' => $this->nn($r->fr_regis_province),
                'surrender_date' => $this->nn($r->fr_surrender_date),
                'surrender_reason' => $this->nn($r->fr_surrender_reason),
                'registered_at' => $this->nn($r->fr_regis_uploaded_at),
                'status' => $this->nn($r->fr_status) ?? 'Active',
                'latitude' => $r->fr_latitude,
                'longitude' => $r->fr_longitude,
                'occupation' => $this->nn($r->fr_occupation),
                'work_status' => $this->nn($r->fr_work_status),
            ])->all());

        $this->insertChunked('fr_program_statuses', $this->legacy->table('fr_program_status')->get()
            ->map(fn ($r) => [
                'id' => $r->status_id,
                'former_rebel_id' => $r->fr_regis_id,
                'reintegration_status' => $this->nn($r->reintegration_status),
                'reintegration_date' => $this->nn($r->reintegration_date),
                'updated_by' => $r->updated_by,
                'created_at' => $this->nn($r->updated_at),
                'updated_at' => $this->nn($r->updated_at),
            ])->all());

        $this->insertChunked('fr_education_works', $this->legacy->table('fr_education_work')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'former_rebel_id' => $r->fr_regis_id,
                'educational_attainment' => $r->fr_educational_attainment,
                'occupation' => $r->fr_occupation,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ])->all());

        $this->insertChunked('fr_location_histories', $this->legacy->table('fr_location_history')->get()
            ->map(fn ($r) => [
                'id' => $r->history_id,
                'former_rebel_id' => $r->fr_regis_id,
                'placement_address' => $r->fr_placement_address,
                'latitude' => $r->fr_latitude,
                'longitude' => $r->fr_longitude,
                'updated_by' => $r->updated_by,
                'created_at' => $r->updated_at,
                'updated_at' => $r->updated_at,
            ])->all());

        $this->insertChunked('fr_skills', $this->legacy->table('fr_skills')->get()
            ->map(fn ($r) => [
                'id' => $r->skill_id,
                'former_rebel_id' => $r->fr_regis_id,
                'skill_name' => $r->skill_name,
                'proficiency_level' => $this->nn($r->proficiency_level),
                'created_at' => $r->added_at,
                'updated_at' => $r->added_at,
            ])->all());

        $this->insertChunked('fr_government_assistances', $this->legacy->table('fr_government_assistance')->get()
            ->map(fn ($r) => [
                'id' => $r->assistance_id,
                'former_rebel_id' => $r->fr_regis_id,
                'assistance_type' => $r->assistance_type,
                'date_received' => $this->nn($r->date_received),
                'status' => $this->nn($r->status),
                'certificate_file' => $r->certificate_file,
                'created_at' => $this->nn($r->added_at),
                'updated_at' => $this->nn($r->added_at),
            ])->all());
    }

    private function importMap(): void
    {
        $this->insertChunked('map_barangays', $this->legacy->table('frmap_barangays')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'fid' => $r->fid,
                'province' => $r->province,
                'municipality' => $r->municipality,
                'barangay' => $r->barangay,
                'frs' => (int) ($r->frs ?: 0),
                'status' => $r->status,
                'infestation_color' => $r->infestation_color,
                'rebels' => (int) ($r->rebels ?: 0),
                'created_at' => $r->last_modified,
                'updated_at' => $r->last_modified,
            ])->all());

        $this->insertChunked('color_histories', $this->legacy->table('color_history')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'map_barangay_id' => $r->barangay_id,
                'status' => $r->status,
                'color' => $r->color,
                'frs' => $r->frs,
                'created_at' => $r->timestamp,
                'updated_at' => $r->timestamp,
            ])->all());
    }

    private function importRcsp(): void
    {
        $this->insertChunked('rcsp_phases', $this->legacy->table('fr_rscp_phase')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_phase_id,
                'name' => $r->fr_rcsp_phase_name,
                'number' => $r->fr_rcsp_phase_num,
            ])->all());

        $this->insertChunked('rcsp_activities', $this->legacy->table('fr_rscp_activity')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_activity_id,
                'rcsp_phase_id' => $r->fr_rcsp_phase_id,
                'description' => $r->fr_rcsp_activity_des,
            ])->all());

        $this->insertChunked('rcsp_barangays', $this->legacy->table('fr_rscp_barangay')->get()
            ->map(fn ($r) => [
                'id' => $r->fr_rscp_bgy_id,
                'barangay_id' => $r->fr_barangay_id,
                'municipality_id' => $r->fr_municipal_id,
                'status' => $this->nn($r->fr_rscp_bgy_status) ?? 'Pending',
                'current_phase' => $r->fr_current_phase ?? 0,
                'created_at' => $this->nn($r->fr_rscp_bgy_uploaded),
                'updated_at' => $this->nn($r->fr_rscp_bgy_uploaded),
            ])->all());

        // Only phase-status rows whose barangay survived.
        $bgyIds = DB::table('rcsp_barangays')->pluck('id')->flip();
        $this->insertChunked('rcsp_phase_statuses', $this->legacy->table('rscp_phases_status')->get()
            ->filter(fn ($r) => isset($bgyIds[$r->fr_rscp_bgy_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rscp_phase_status_id,
                'rcsp_barangay_id' => $r->fr_rscp_bgy_id,
                'phase0_completed' => $r->phase0_completed,
                'phase1_completed' => $r->phase1_completed,
                'phase2_completed' => $r->phase2_completed,
                'phase3_completed' => $r->phase3_completed,
                'phase4_completed' => $r->phase4_completed,
                'phase5_completed' => $r->phase5_completed,
            ])->values()->all());

        // Forms: fr_lgu_id here is already a kp_user_id. Keep only resolvable refs.
        $userIds = DB::table('users')->pluck('id')->flip();
        $actIds = DB::table('rcsp_activities')->pluck('id')->flip();
        $phaseIds = DB::table('rcsp_phases')->pluck('id')->flip();
        $skippedForms = 0;
        $forms = $this->legacy->table('fr_rscp_form')->get()
            ->filter(function ($r) use ($userIds, $bgyIds, $actIds, $phaseIds, &$skippedForms) {
                $ok = isset($userIds[$r->fr_lgu_id], $bgyIds[$r->fr_rscp_bgy_id],
                    $actIds[$r->fr_rcsp_activity_id], $phaseIds[$r->fr_rcsp_phase_id]);
                if (! $ok) {
                    $skippedForms++;
                }
                return $ok;
            })
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_form_id,
                'lgu_user_id' => $r->fr_lgu_id,
                'rcsp_barangay_id' => $r->fr_rscp_bgy_id,
                'rcsp_phase_id' => $r->fr_rcsp_phase_id,
                'rcsp_activity_id' => $r->fr_rcsp_activity_id,
                'conduct' => $this->nn($r->fr_rcsp_form_conduct),
                'file' => $this->nn($r->fr_rcsp_form_file),
                'status' => $this->nn($r->fr_rcsp_form_status) ?? 'submitted',
                'remarks' => $this->nn($r->fr_rcsp_form_remarks),
                'created_at' => $r->fr_rcsp_form_created,
                'updated_at' => $r->fr_rcsp_form_created,
            ])->values()->all();
        $this->insertChunked('rcsp_forms', $forms);
        if ($skippedForms) {
            $this->warn("  rcsp_forms: skipped {$skippedForms} rows with dangling references.");
        }

        $formIds = DB::table('rcsp_forms')->pluck('id')->flip();
        $this->insertChunked('rcsp_file_comments', $this->legacy->table('fr_rscp_file_comments')->get()
            ->filter(fn ($r) => $r->fr_rcsp_form_id === null || isset($formIds[$r->fr_rcsp_form_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_com_id,
                'rcsp_form_id' => $r->fr_rcsp_form_id,
                'rcsp_phase_id' => isset($phaseIds[$r->fr_rcsp_phase_id]) ? $r->fr_rcsp_phase_id : null,
                'rcsp_activity_id' => isset($actIds[$r->fr_rcsp_activity_id]) ? $r->fr_rcsp_activity_id : null,
                'user_id' => isset($userIds[$r->fr_rcsp_user_id]) ? $r->fr_rcsp_user_id : null,
                'text' => $r->fr_rcsp_com_text,
                'created_at' => $r->fr_rcsp_com_created,
                'updated_at' => $r->fr_rcsp_com_created,
            ])->values()->all());

        $this->insertChunked('rcsp_lgu_comments', $this->legacy->table('fr_rscp_lgu_comments')->get()
            ->filter(fn ($r) => $r->fr_rcsp_form_id === null || isset($formIds[$r->fr_rcsp_form_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_lgu_com_id,
                'rcsp_form_id' => $r->fr_rcsp_form_id,
                'rcsp_phase_id' => isset($phaseIds[$r->fr_rcsp_phase_id]) ? $r->fr_rcsp_phase_id : null,
                'rcsp_activity_id' => isset($actIds[$r->fr_rcsp_activity_id]) ? $r->fr_rcsp_activity_id : null,
                'user_id' => isset($userIds[$r->kp_user_id]) ? $r->kp_user_id : null,
                'text' => $r->fr_rcsp_lgu_com_text,
                'created_at' => $r->fr_rcsp_lgu_com_created,
                'updated_at' => $r->fr_rcsp_lgu_com_created,
            ])->values()->all());
    }

    private function csvToJson(?string $v): string
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', (string) $v)), fn ($x) => $x !== ''));
        return json_encode(array_map('intval', $ids));
    }

    private function importImplementations($lguRowToUser, $kpAgencyToAgency): void
    {
        $userIds = DB::table('users')->pluck('id')->flip();

        $impls = $this->legacy->table('fr_rscp_implementation')->get()
            ->map(function ($r) use ($lguRowToUser, $userIds) {
                $uid = $lguRowToUser[$r->fr_lgu_id] ?? null;
                if (! isset($userIds[$uid])) {
                    $uid = DB::table('users')->where('role', 'lgu')->value('id'); // fallback
                }
                return [
                    'id' => $r->fr_rcsp_imp_id,
                    'lgu_user_id' => $uid,
                    'uploaded_at' => $this->nn($r->fr_rcsp_imp_uploaded_at),
                    'issues' => $this->nn($r->fr_rcsp_imp_issues),
                    'program' => $this->nn($r->fr_rcsp_imp_program),
                    'target_areas' => $this->csvToJson($r->fr_rcsp_imp_area),
                    'agencies' => $this->csvToJson($r->fr_rcsp_imp_agency),
                    'beneficiaries' => $this->nn($r->fr_rcsp_imp_beneficiaries),
                    'outcome' => $this->nn($r->fr_rcsp_imp_outcome),
                    'resources' => $this->nn($r->fr_rcsp_imp_resources),
                    'support' => $this->nn($r->fr_rcsp_imp_support),
                    'duration' => $this->nn($r->fr_rcsp_imp_duration),
                    'status' => $this->nn($r->fr_rcsp_imp_status) ?? 'not yet started',
                    'type_gov' => $this->nn($r->fr_rcsp_imp_type_gov),
                    'sources' => $this->nn($r->fr_rcsp_imp_sources),
                    'remarks' => $this->nn($r->fr_rcsp_imp_remarks),
                    'tagging' => $this->nn($r->fr_rcsp_imp_tagging),
                ];
            })->all();

        // Legacy data has no trustworthy parent/header key, so preserve every
        // historical baseline row and give it a separate parent instead of
        // guessing which old rows belonged to one submission.
        if (Schema::hasTable('implementation_plans')) {
            foreach ($impls as &$impl) {
                $impl['implementation_plan_id'] = DB::table('implementation_plans')->insertGetId([
                    'lgu_user_id' => $impl['lgu_user_id'],
                    'title' => 'Legacy IMPLAN #'.$impl['id'],
                    'status' => $impl['status'],
                    'submitted_at' => $impl['uploaded_at'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            unset($impl);
        }
        $this->insertChunked('implementations', $impls);

        $implIds = DB::table('implementations')->pluck('id')->flip();
        $agencyIds = DB::table('gov_agencies')->pluck('id')->flip();

        $this->insertChunked('implementation_files', $this->legacy->table('fr_rscp_implan_file')->get()
            ->filter(fn ($r) => isset($implIds[$r->fr_rcsp_imp_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rscp_implan_file_id,
                'implementation_id' => $r->fr_rcsp_imp_id,
                'file_name' => $r->fr_rcsp_imp_file_name,
                'description' => $this->nn($r->fr_rcsp_imp_description),
                'pdf' => $r->fr_rcsp_imp_pdf,
            ])->values()->all());

        $this->insertChunked('implementation_photos', $this->legacy->table('fr_rscp_implan_photo')->get()
            ->filter(fn ($r) => isset($implIds[$r->fr_rcsp_imp_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rcsp_photo_id,
                'implementation_id' => $r->fr_rcsp_imp_id,
                'image' => $r->fr_rcsp_photo_image,
                'created_at' => $r->fr_rcsp_photo_uploaded_at,
                'updated_at' => $r->fr_rcsp_photo_uploaded_at,
            ])->values()->all());

        // Responses: agency_id is a kp_gov_agency id -> map to real agency.
        $respSkip = 0;
        $responses = $this->legacy->table('agency_implan_responses')->get()
            ->map(function ($r) use ($implIds, $kpAgencyToAgency, $agencyIds, &$respSkip) {
                $aid = $kpAgencyToAgency[$r->agency_id] ?? null;
                if (! isset($implIds[$r->implan_id]) || ! isset($agencyIds[$aid])) {
                    $respSkip++;
                    return null;
                }
                return [
                    'gov_agency_id' => $aid,
                    'implementation_id' => $r->implan_id,
                    'response_status' => strtolower($this->nn($r->response_status) ?? 'pending'),
                    'rejection_reason' => $r->rejection_reason,
                    'created_at' => $r->response_date,
                    'updated_at' => $r->response_date,
                ];
            })->filter()->values()
            // one row per (agency, implan) — dedupe to satisfy unique key
            ->unique(fn ($r) => $r['gov_agency_id'].'-'.$r['implementation_id'])->values()->all();
        $this->insertChunked('agency_implan_responses', $responses);
        if ($respSkip) {
            $this->warn("  agency_implan_responses: skipped {$respSkip} orphan/unmapped rows.");
        }

        // Taggings: kp_gov_agency_id -> real agency.
        $this->insertChunked('implementation_taggings', $this->legacy->table('fr_rscp_tagging')->get()
            ->filter(fn ($r) => isset($implIds[$r->fr_rcsp_imp_id], $kpAgencyToAgency[$r->kp_gov_agency_id]))
            ->map(fn ($r) => [
                'id' => $r->fr_rscp_tag_id,
                'implementation_id' => $r->fr_rcsp_imp_id,
                'gov_agency_id' => $kpAgencyToAgency[$r->kp_gov_agency_id],
                'status' => $this->nn($r->fr_rscp_tag_status) ?? 'Pending',
                'reason' => $this->nn($r->fr_rscp_tag_reason),
                'created_at' => $this->nn($r->fr_rscp_tag_uploaded_at),
                'updated_at' => $this->nn($r->fr_rscp_tag_uploaded_at),
            ])->values()->all());
    }

    private function insertChunked(string $table, array $rows): void
    {
        if (! $rows) {
            $this->line("  {$table}: 0");
            return;
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->insert($chunk);
        }
        $this->line("  {$table}: ".count($rows));
    }
}
