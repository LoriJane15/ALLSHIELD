<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrDocumentSource;
use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FeaComplianceStatus;
use App\Enums\Ib39FeaDocumentStatus;
use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Enums\PswdoEnrollmentDocumentType;
use App\Models\FormerRebel;
use App\Models\FrProgramStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\SurfacedFrProgressTimelineService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class SurfacedFrProgressTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_maps_authoritative_pending_ongoing_completed_locked_na_and_not_started_states(): void
    {
        [$record, $ib39] = $this->record(false);

        $this->assertPhase($record, 'cdr', 'pending', 'Pending', true);
        $this->assertPhase($record, 'japic', 'locked', 'Locked');
        $this->assertPhase($record, 'fea', 'not-applicable', 'Not Applicable');
        $this->assertPhase($record, 'reintegration', 'locked', 'Locked');

        $record->cdrProcessing()->update(['status' => Ib39CdrStatus::Ongoing]);
        $this->assertPhase($record, 'cdr', 'ongoing', 'Ongoing', true);

        $cdrFinal = $this->completeCdr($record, $ib39);
        $japic = User::factory()->role('japic')->create();
        $processing = $this->createJapic($record, $cdrFinal, $japic);
        $this->assertPhase($record, 'cdr', 'completed', 'Completed');
        $this->assertPhase($record, 'japic', 'pending', 'Pending', true);

        $processing->update(['status' => JapicCertificationStatus::Drafting]);
        $this->assertPhase($record, 'japic', 'ongoing', 'Ongoing', true);

        $this->completeJapic($processing, $japic);
        $enrollment = PswdoEnrollment::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'lock_version' => 0,
        ]);
        $this->assertPhase($record, 'japic', 'completed', 'Completed');
        $this->assertPhase($record, 'pswdo', 'pending', 'Pending', true);

        $this->addPswdoDocuments($enrollment, $japic, [PswdoEnrollmentDocumentType::EclipEnrollmentForm]);
        $this->assertPhase($record, 'pswdo', 'ongoing', 'Ongoing', true);

        $this->addPswdoDocuments($enrollment, $japic, array_slice(PswdoEnrollmentDocumentType::cases(), 1));
        $this->assertPhase($record, 'pswdo', 'completed', 'Completed');
        $this->assertPhase($record, 'fea', 'not-applicable', 'Not Applicable');
        $this->assertPhase($record, 'reintegration', 'not-started', 'Not Started', true);
    }

    public function test_foreign_owned_or_missing_final_documents_cannot_complete_cdr_or_japic(): void
    {
        [$record, $ib39] = $this->record(false);
        [$other, $otherIb39] = $this->record(false);
        $foreignCdrFinal = $this->completeCdr($other, $otherIb39);

        try {
            $record->cdrProcessing()->update([
                'status' => Ib39CdrStatus::Completed,
                'current_final_version_id' => $foreignCdrFinal->id,
                'completed_at' => now(),
                'completed_by' => $ib39->id,
            ]);
            $this->fail('The database accepted a foreign-owned CDR final document.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $loadedWithForeignCdr = $this->loadedRecord($record);
        $loadedWithForeignCdr->cdrProcessing->forceFill(['status' => Ib39CdrStatus::Completed]);
        $loadedWithForeignCdr->cdrProcessing->setRelation('currentFinalVersion', $foreignCdrFinal);
        $foreignCdrTimeline = app(SurfacedFrProgressTimelineService::class)->timeline($loadedWithForeignCdr);
        $this->assertSame('Unavailable', $this->phase($foreignCdrTimeline, 'cdr')['status']);

        $ownCdrFinal = $record->cdrProcessing->documentVersions()->create($this->cdrDocumentAttributes($ib39));
        $record->cdrProcessing()->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $ib39->id,
            'current_final_version_id' => $ownCdrFinal->id,
        ]);
        $japic = User::factory()->role('japic')->create();
        $otherJapic = User::factory()->role('japic')->create();
        $processing = $this->createJapic($record, $ownCdrFinal, $japic);
        $otherProcessing = $this->createJapic($other, $foreignCdrFinal, $otherJapic);
        $foreignJapicFinal = $this->japicFinal($otherProcessing, $otherJapic);

        try {
            $processing->forceFill([
                'status' => JapicCertificationStatus::Completed,
                'current_final_version_id' => $foreignJapicFinal->id,
                'completed_at' => now(),
                'completed_by' => $japic->id,
            ])->save();
            $this->fail('The database accepted a foreign-owned JAPIC final document.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $loadedWithForeignJapic = $this->loadedRecord($record);
        $loadedWithForeignJapic->japicCertificationProcessing->forceFill(['status' => JapicCertificationStatus::Completed]);
        $loadedWithForeignJapic->japicCertificationProcessing->setRelation('currentFinalVersion', $foreignJapicFinal);
        $foreignJapicTimeline = app(SurfacedFrProgressTimelineService::class)->timeline($loadedWithForeignJapic);
        $this->assertSame('Completed', $this->phase($foreignJapicTimeline, 'cdr')['status']);
        $this->assertSame('Unavailable', $this->phase($foreignJapicTimeline, 'japic')['status']);
    }

    public function test_firearms_fea_uses_existing_document_status_and_only_unlocks_reintegration_when_completed(): void
    {
        [$record, $ib39] = $this->record(true);
        $japic = User::factory()->role('japic')->create();
        $cdrFinal = $this->completeCdr($record, $ib39);
        $processing = $this->createJapic($record, $cdrFinal, $japic);
        $this->completeJapic($processing, $japic);
        $enrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id]);
        $this->addPswdoDocuments($enrollment, $japic, PswdoEnrollmentDocumentType::cases());

        $this->assertPhase($record, 'fea', 'pending', 'Pending', true);
        $this->assertPhase($record, 'reintegration', 'locked', 'Locked');

        $document = $record->feaProcessing()->firstOrFail()->documents()->firstOrFail();
        $document->update(['status' => Ib39FeaDocumentStatus::Processing]);
        $this->assertPhase($record, 'fea', 'ongoing', 'Ongoing', true);

        $document->update(['compliance_status' => Ib39FeaComplianceStatus::ReturnedForCompliance]);
        $this->assertPhase($record, 'fea', 'ongoing', 'For Compliance', true);

        $record->feaProcessing()->firstOrFail()->documents()->update([
            'status' => Ib39FeaDocumentStatus::Completed,
            'compliance_status' => Ib39FeaComplianceStatus::None,
        ]);
        $this->assertPhase($record, 'fea', 'completed', 'Completed');
        $this->assertPhase($record, 'reintegration', 'not-started', 'Not Started', true);
    }

    public function test_cancellation_and_unavailable_workflows_are_never_presented_as_completed(): void
    {
        [$cancelled, $actor] = $this->record(true);
        DB::table('ib39_fr_cancellations')->insert([
            'ib39_surfaced_former_rebel_id' => $cancelled->id,
            'previous_overall_status' => Ib39SurfacedFormerRebel::OVERALL_CASE_STATUS,
            'reason' => encrypt('Approved test cancellation.'),
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertPhase($cancelled, 'cdr', 'cancelled', 'Cancelled', true);
        $this->assertPhase($cancelled, 'japic', 'locked', 'Locked');
        $this->assertNotContains('completed', collect($this->timeline($cancelled))->pluck('state')->all());

        [$unavailable, $ib39] = $this->record(false);
        $unavailable->cdrProcessing()->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $ib39->id,
            'current_final_version_id' => null,
        ]);
        $this->assertPhase($unavailable, 'cdr', 'unavailable', 'Unavailable', true);
    }

    public function test_reintegration_ignores_unrelated_legacy_mblrc_completion_records(): void
    {
        [$record, $ib39] = $this->record(false);
        $japic = User::factory()->role('japic')->create();
        $cdrFinal = $this->completeCdr($record, $ib39);
        $processing = $this->createJapic($record, $cdrFinal, $japic);
        $this->completeJapic($processing, $japic);
        $enrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id]);
        $this->addPswdoDocuments($enrollment, $japic, PswdoEnrollmentDocumentType::cases());

        $legacy = FormerRebel::query()->forceCreate([
            'classified_id' => $record->reference_number,
            'firstname' => $record->first_name,
            'lastname' => $record->last_name,
        ]);
        FrProgramStatus::query()->create([
            'former_rebel_id' => $legacy->id,
            'reintegration_status' => 'Completed',
            'reintegration_date' => now()->toDateString(),
        ]);

        $loaded = $this->loadedRecord($record);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $timeline = app(SurfacedFrProgressTimelineService::class)->timeline($loaded);

        $this->assertSame('Not Started', $this->phase($timeline, 'reintegration')['status']);
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => str_contains($sql, 'former_rebels') || str_contains($sql, 'fr_program_statuses'),
        ));
        $this->assertSame([], $queries, 'Timeline presentation triggered an unexpected database query.');
    }

    public function test_service_rejects_missing_eager_loaded_relationships(): void
    {
        [$record] = $this->record(false);

        $this->expectException(LogicException::class);
        app(SurfacedFrProgressTimelineService::class)->timeline($record->fresh());
    }

    public function test_same_accessible_ordered_timeline_renders_on_all_three_authorized_profiles_without_changing_authorization(): void
    {
        [$record, $ib39] = $this->record(false);
        $japic = User::factory()->role('japic')->create();
        $pswdo = User::factory()->role('pswdo')->create();
        $cdrFinal = $this->completeCdr($record, $ib39);
        $processing = $this->createJapic($record, $cdrFinal, $japic);
        $this->completeJapic($processing, $japic);
        $enrollment = PswdoEnrollment::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id]);
        $orderedLabels = ['CDR', 'JAPIC Certification', 'PSWDO Enrollment', 'FEA Processing', 'Reintegration'];

        $profiles = [
            [$ib39, route('ib39.fr-profiles.show', $record)],
            [$japic, route('japic.certifications.show', $processing)],
            [$pswdo, route('pswdo.enrollments.show', $enrollment)],
        ];

        foreach ($profiles as [, $url]) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($profiles as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('Surfaced FR Progress')
                ->assertSee('aria-label="Surfaced former rebel workflow progress"', false)
                ->assertSee('aria-current="step"', false)
                ->assertSeeInOrder($orderedLabels);
        }

        $this->actingAs($pswdo)->get(route('ib39.fr-profiles.show', $record))->assertForbidden();
        $this->actingAs($ib39)->get(route('japic.certifications.show', $processing))->assertForbidden();
        $this->actingAs($japic)->get(route('pswdo.enrollments.show', $enrollment))->assertForbidden();
    }

    /** @return array{Ib39SurfacedFormerRebel, User} */
    private function record(bool $possessedFirearms): array
    {
        $ib39 = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => fake()->unique()->city()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Timeline',
            'last_name' => 'Subject',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id,
            'surfaced_at' => '2026-09-12',
            'possessed_firearms' => $possessedFirearms,
        ], $ib39);

        return [$record, $ib39];
    }

    private function completeCdr(Ib39SurfacedFormerRebel $record, User $actor): Ib39CdrDocumentVersion
    {
        $final = $record->cdrProcessing()->firstOrFail()->documentVersions()->create($this->cdrDocumentAttributes($actor));
        $record->cdrProcessing()->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'current_final_version_id' => $final->id,
        ]);

        return $final;
    }

    /** @return array<string, mixed> */
    private function cdrDocumentAttributes(User $actor): array
    {
        return [
            'version_number' => 1,
            'source_type' => Ib39CdrDocumentSource::Uploaded,
            'storage_path' => 'tests/timeline/cdr-'.fake()->unique()->uuid().'.pdf',
            'original_filename' => 'final-cdr.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'created_by' => $actor->id,
            'finalized_at' => now(),
        ];
    }

    private function createJapic(
        Ib39SurfacedFormerRebel $record,
        Ib39CdrDocumentVersion $cdrFinal,
        User $actor,
    ): JapicCertificationProcessing {
        return JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_document_version_id' => $cdrFinal->id,
            'status' => JapicCertificationStatus::Pending,
            'received_at' => now(),
            'due_at' => now()->addDays(14),
            'assigned_to' => $actor->id,
            'lock_version' => 0,
        ]);
    }

    private function completeJapic(JapicCertificationProcessing $processing, User $actor): JapicCertificationDocumentVersion
    {
        $final = $this->japicFinal($processing, $actor);
        $processing->forceFill([
            'status' => JapicCertificationStatus::Completed,
            'current_final_version_id' => $final->id,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ])->save();

        return $final;
    }

    private function japicFinal(JapicCertificationProcessing $processing, User $actor): JapicCertificationDocumentVersion
    {
        return $processing->documentVersions()->create([
            'version_number' => 1,
            'storage_path' => 'tests/timeline/japic-'.fake()->unique()->uuid().'.pdf',
            'original_filename' => 'final-japic.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 20,
            'sha256' => hash('sha256', fake()->unique()->uuid()),
            'uploaded_by' => $actor->id,
            'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true,
            'uploaded_at' => now(),
        ]);
    }

    /** @param array<int, PswdoEnrollmentDocumentType> $types */
    private function addPswdoDocuments(PswdoEnrollment $enrollment, User $actor, array $types): void
    {
        foreach ($types as $type) {
            $enrollment->documents()->create([
                'document_type' => $type,
                'storage_path' => "tests/timeline/pswdo-{$enrollment->id}-{$type->value}.pdf",
                'original_filename' => $type->value.'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 20,
                'sha256' => hash('sha256', "{$enrollment->id}-{$type->value}"),
                'uploaded_by' => $actor->id,
                'correct_document_type_confirmed' => true,
                'belongs_to_fr_confirmed' => true,
                'final_signed_confirmed' => true,
                'uploaded_at' => now(),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function timeline(Ib39SurfacedFormerRebel $record): array
    {
        return app(SurfacedFrProgressTimelineService::class)->timeline($this->loadedRecord($record));
    }

    private function loadedRecord(Ib39SurfacedFormerRebel $record): Ib39SurfacedFormerRebel
    {
        return $record->fresh()->load([
            'cdrProcessing.currentFinalVersion',
            'japicCertificationProcessing.currentFinalVersion',
            'pswdoEnrollment.documents',
            'feaProcessing.documents',
            'cancellation',
        ]);
    }

    /** @param array<int, array<string, mixed>> $timeline */
    private function phase(array $timeline, string $key): array
    {
        return collect($timeline)->firstWhere('key', $key);
    }

    private function assertPhase(
        Ib39SurfacedFormerRebel $record,
        string $key,
        string $state,
        string $status,
        bool $isCurrent = false,
    ): void {
        $phase = $this->phase($this->timeline($record), $key);
        $this->assertSame($state, $phase['state']);
        $this->assertSame($status, $phase['status']);
        $this->assertSame($isCurrent, $phase['is_current']);
    }
}
