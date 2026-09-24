<?php

namespace Database\Seeders;

use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\AgencyImplanResponse;
use App\Models\Barangay;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ColorHistory;
use App\Models\FormerRebel;
use App\Models\FrEducationWork;
use App\Models\FrGovernmentAssistance;
use App\Models\FrLocationHistory;
use App\Models\FrProgramStatus;
use App\Models\FrSkill;
use App\Models\GovAgency;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Implementation;
use App\Models\ImplementationTagging;
use App\Models\JapicCertificationProcessing;
use App\Models\MapBarangay;
use App\Models\Municipality;
use App\Models\RcspFileComment;
use App\Models\RcspForm;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Database\Seeder;
use RuntimeException;

class LocalInterconnectedDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Interconnected fixture seeding is permitted only in the automated test environment.');
        }

        $demoPassword = 'Shield-Local-2026-Test!';
        putenv("RCSP_DEMO_PASSWORD={$demoPassword}");
        $_ENV['RCSP_DEMO_PASSWORD'] = $demoPassword;
        $_SERVER['RCSP_DEMO_PASSWORD'] = $demoPassword;
        $this->call(RcspDemoSeeder::class);

        $municipality = Municipality::firstOrCreate(['name' => 'DEMO Municipality']);
        $agency = GovAgency::firstOrCreate(
            ['acronym' => 'DGA'],
            ['name' => 'Demo Government Agency'],
        );
        $partnerAgency = GovAgency::firstOrCreate(
            ['acronym' => 'DILG-DEMO'],
            ['name' => 'Demo DILG Partner Agency'],
        );

        $lgu = User::where('username', 'lgu_officer')->firstOrFail();
        $reviewer = User::where('username', 'katuparan_admin')->firstOrFail();
        $agencyUser = User::where('username', 'agency_officer')->firstOrFail();
        $lgu->update(['municipality_id' => $municipality->id]);
        $agencyUser->update(['gov_agency_id' => $agency->id]);

        $barangays = collect(['Pending', 'Submitted', 'In-Progress', 'Completed'])
            ->mapWithKeys(fn (string $state) => [$state => Barangay::firstOrCreate([
                'municipality_id' => $municipality->id,
                'name' => "DEMO {$state} Barangay",
            ])]);

        $profiles = [
            ['FR-#9001', 'Alex', 'Demo', 'Male', 35, 'Reintegrated', 'DEMO Completed Barangay', 'Carpenter', 6.98010000, 125.17010000],
            ['FR-#9002', 'Sam', 'Sample', 'Female', 29, 'Active', 'DEMO In-Progress Barangay', 'Community gardener', 6.98230000, 125.17320000],
            ['FR-#9003', 'Jordan', 'Example', 'Male', 42, 'Under Review', 'DEMO Submitted Barangay', 'Driver', 6.98490000, 125.17680000],
        ];

        foreach ($profiles as [$classifiedId, $first, $last, $gender, $age, $status, $barangayName, $occupation, $latitude, $longitude]) {
            $barangay = $barangays->first(fn (Barangay $record) => $record->name === $barangayName);
            $formerRebel = FormerRebel::updateOrCreate(
                ['classified_id' => $classifiedId],
                [
                    'firstname' => $first,
                    'lastname' => $last,
                    'gender' => $gender,
                    'age' => $age,
                    'civil_status' => 'Married',
                    'residential_address' => "DEMO address, {$barangayName}",
                    'placement_address' => "DEMO placement, {$barangayName}",
                    'barangay_id' => $barangay->id,
                    'municipality_id' => $municipality->id,
                    'province' => 'DEMO Province',
                    'surrender_date' => now()->subYears(3)->toDateString(),
                    'surrender_reason' => 'DEMO record for local workflow testing only.',
                    'registered_at' => now()->subYears(2)->toDateString(),
                    'status' => $status,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'occupation' => $occupation,
                    'work_status' => 'DEMO active monitoring',
                ],
            );

            FrProgramStatus::updateOrCreate(['former_rebel_id' => $formerRebel->id], [
                'reintegration_status' => $status === 'Reintegrated' ? 'Completed' : 'On-going',
                'reintegration_date' => now()->subMonths(4)->toDateString(),
                'updated_by' => $reviewer->name,
            ]);
            FrEducationWork::updateOrCreate(['former_rebel_id' => $formerRebel->id], [
                'educational_attainment' => 'DEMO secondary education',
                'occupation' => $occupation,
            ]);
            FrLocationHistory::updateOrCreate(['former_rebel_id' => $formerRebel->id], [
                'placement_address' => "DEMO placement, {$barangayName}",
                'latitude' => $latitude,
                'longitude' => $longitude,
                'updated_by' => $reviewer->name,
            ]);
            FrSkill::firstOrCreate([
                'former_rebel_id' => $formerRebel->id,
                'skill_name' => 'DEMO livelihood skill',
            ], ['proficiency_level' => 'Intermediate']);
            FrGovernmentAssistance::updateOrCreate([
                'former_rebel_id' => $formerRebel->id,
                'assistance_type' => 'DEMO livelihood support',
            ], [
                'date_received' => now()->subMonths(2)->toDateString(),
                'status' => $status === 'Under Review' ? 'Pending' : 'Completed',
            ]);
        }

        foreach ($barangays as $barangay) {
            $frs = $barangay->name === 'DEMO Completed Barangay' ? 8 : ($barangay->name === 'DEMO In-Progress Barangay' ? 12 : 5);
            ['status' => $mapStatus, 'color' => $mapColor] = MapBarangay::classify($frs);
            $mapBarangay = MapBarangay::updateOrCreate(['fid' => "DEMO-{$barangay->id}"], [
                'province' => 'DEMO Province',
                'municipality' => $municipality->name,
                'barangay' => $barangay->name,
                'frs' => $frs,
                'status' => $mapStatus,
                'infestation_color' => $mapColor,
                'rebels' => 0,
            ]);
            ColorHistory::firstOrCreate([
                'map_barangay_id' => $mapBarangay->id,
                'status' => $mapStatus,
                'color' => $mapColor,
                'frs' => $frs,
            ]);
        }

        $implementation = Implementation::updateOrCreate([
            'lgu_user_id' => $lgu->id,
            'program' => 'DEMO Community Livelihood and Access Plan',
        ], [
            'uploaded_at' => now()->subDays(12)->toDateString(),
            'issues' => 'DEMO: Limited access to livelihood resources and transport.',
            'target_areas' => [$barangays['In-Progress']->id, $barangays['Completed']->id],
            'agencies' => [$agency->id, $partnerAgency->id],
            'beneficiaries' => 'DEMO households, including monitored former-rebel families.',
            'outcome' => 'DEMO improved livelihood access and local coordination.',
            'resources' => 'DEMO training materials and transport support.',
            'support' => 'DEMO LGU and partner-agency coordination.',
            'duration' => 'DEMO 6-month rollout',
            'status' => 'for verification',
            'type_gov' => 'NGA',
            'sources' => 'DEMO planning workshop',
            'remarks' => 'DEMO record linked to the selected RCSP barangays.',
            'tagging' => 'Accepted',
        ]);
        foreach ([$agency, $partnerAgency] as $assignedAgency) {
            AgencyImplanResponse::updateOrCreate([
                'gov_agency_id' => $assignedAgency->id,
                'implementation_id' => $implementation->id,
            ], ['response_status' => 'accepted']);
            ImplementationTagging::updateOrCreate([
                'implementation_id' => $implementation->id,
                'gov_agency_id' => $assignedAgency->id,
            ], ['status' => 'Accepted', 'reason' => 'DEMO agency assignment accepted.']);
        }

        $form = RcspForm::query()->where('lgu_user_id', User::where('username', 'rcsp_lgu_demo')->value('id'))->first();
        if ($form) {
            RcspFileComment::firstOrCreate([
                'rcsp_form_id' => $form->id,
                'user_id' => $reviewer->id,
                'text' => 'DEMO: RCSP evidence reviewed alongside the connected IMPLAN record.',
            ], [
                'rcsp_phase_id' => $form->rcsp_phase_id,
                'rcsp_activity_id' => $form->rcsp_activity_id,
            ]);
        }

        $conversation = ChatConversation::firstOrCreate([
            'user_one_id' => min($lgu->id, $agencyUser->id),
            'user_two_id' => max($lgu->id, $agencyUser->id),
        ]);
        $message = ChatMessage::firstOrCreate([
            'chat_conversation_id' => $conversation->id,
            'sender_id' => $lgu->id,
            'body' => 'DEMO: The community livelihood plan is ready for agency coordination.',
        ]);
        $conversation->readStates()->firstOrCreate(
            ['user_id' => $lgu->id],
            ['last_read_message_id' => $message->id],
        );
        $conversation->readStates()->firstOrCreate(
            ['user_id' => $agencyUser->id],
            ['last_read_message_id' => $message->id],
        );

        // Seed 39th IB Surfaced Former Rebels & Completed JAPIC / PSWDO Records
        $ib39Officer = User::where('username', 'ib39_officer')->first();
        $japicOfficer = User::where('username', 'japic_officer')->first();
        $pswdoOfficer = User::where('username', 'pswdo_officer')->first();

        if ($ib39Officer && $japicOfficer) {
            $surfacedSamples = [
                ['Juan', 'Dela Cruz', 'Regular Member', 'DEMO Completed Barangay', true, 'Surfaced with 1 M16 rifle during military operations.'],
                ['Maria', 'Santos', 'Milisya ng Bayan', 'DEMO In-Progress Barangay', false, 'Surfaced peacefully to local authorities.'],
                ['Pedro', 'Penduko', 'UGMOs/Underground Mass Organization', 'DEMO Submitted Barangay', true, 'Voluntarily yielded service weapon.'],
            ];

            $frService = app(Ib39SurfacedFormerRebelService::class);

            foreach ($surfacedSamples as $index => [$firstName, $lastName, $category, $bName, $hasFirearms, $remarks]) {
                $targetBarangay = $barangays->first(fn (Barangay $record) => $record->name === $bName);
                $surfacedFr = Ib39SurfacedFormerRebel::where('first_name', $firstName)
                    ->where('last_name', $lastName)
                    ->first();

                if (! $surfacedFr) {
                    $surfacedFr = $frService->create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'category' => $category,
                        'province' => 'Davao del Sur',
                        'municipality_id' => $municipality->id,
                        'barangay_id' => $targetBarangay?->id,
                        'specific_location' => "Sitio Demo {$index}, {$bName}",
                        'surfaced_at' => now()->subMonths(2)->subDays($index * 5)->toDateString(),
                        'possessed_firearms' => $hasFirearms,
                        'initial_remarks' => $remarks,
                    ], $ib39Officer);
                }

                // Create Completed CDR
                $cdr = $surfacedFr->cdrProcessing ?: $surfacedFr->cdrProcessing()->create([
                    'status' => 'Completed',
                    'completed_at' => now()->subMonths(1),
                    'completed_by' => $ib39Officer->id,
                ]);

                if (! $cdr->current_final_version_id) {
                    $cdrVersion = $cdr->documentVersions()->create([
                        'version_number' => 1,
                        'source_type' => 'uploaded',
                        'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf",
                        'original_filename' => "CDR_{$surfacedFr->reference_number}.pdf",
                        'mime_type' => 'application/pdf',
                        'size_bytes' => 10240,
                        'sha256' => hash('sha256', "demo_cdr_{$surfacedFr->id}"),
                        'created_by' => $ib39Officer->id,
                        'finalized_at' => now()->subMonths(1),
                    ]);
                    $cdr->update(['current_final_version_id' => $cdrVersion->id]);
                }

                // Create Completed JAPIC Certification
                $japic = $surfacedFr->japicCertificationProcessing ?: JapicCertificationProcessing::firstOrCreate(
                    ['ib39_surfaced_former_rebel_id' => $surfacedFr->id],
                    [
                        'triggering_cdr_document_version_id' => $cdr->current_final_version_id,
                        'status' => 'Completed',
                        'received_at' => now()->subMonths(1),
                        'due_at' => now()->subMonths(1)->addDays(14),
                        'completed_at' => now()->subWeeks(2),
                        'completed_by' => $japicOfficer->id,
                        'lock_version' => 1,
                    ]
                );

                if (! $japic->current_final_version_id) {
                    $japicVersion = $japic->documentVersions()->create([
                        'version_number' => 1,
                        'storage_path' => "japic/certifications/{$japic->id}/final-documents/final.pdf",
                        'original_filename' => "JAPIC_CERT_{$surfacedFr->reference_number}.pdf",
                        'mime_type' => 'application/pdf',
                        'size_bytes' => 15360,
                        'sha256' => hash('sha256', "demo_japic_{$surfacedFr->id}"),
                        'uploaded_by' => $japicOfficer->id,
                        'all_signatories_confirmed' => true,
                        'correct_final_confirmed' => true,
                        'uploaded_at' => now()->subWeeks(2),
                    ]);
                    $japic->update(['current_final_version_id' => $japicVersion->id]);
                }

                // Automatically Receive into PSWDO
                $enrollment = app(PswdoEnrollmentIntakeService::class)->receiveEligible($surfacedFr->id);

                // If it's the first sample, add sample documents to make it partially or fully completed
                if ($enrollment && $index === 0 && $pswdoOfficer) {
                    foreach ([PswdoEnrollmentDocumentType::EclipEnrollmentForm, PswdoEnrollmentDocumentType::InitialInterviewForm] as $docType) {
                        if (! $enrollment->hasDocument($docType)) {
                            $enrollment->documents()->create([
                                'document_type' => $docType,
                                'storage_path' => "pswdo/enrollments/{$enrollment->id}/{$docType->value}.pdf",
                                'original_filename' => "{$docType->value}_{$surfacedFr->reference_number}.pdf",
                                'mime_type' => 'application/pdf',
                                'size_bytes' => 12400,
                                'sha256' => hash('sha256', "demo_pswdo_{$docType->value}_{$enrollment->id}"),
                                'uploaded_by' => $pswdoOfficer->id,
                                'uploaded_at' => now()->subDays(3),
                            ]);
                        }
                    }
                }
            }
        }
    }
}
