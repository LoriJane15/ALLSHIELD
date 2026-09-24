<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Enums\JapicCertificationStatus;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\JapicCertificationDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\JapicCertificationDocumentService;
use App\Services\PswdoEligibilityService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PswdoEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_completed_owned_finals_are_required_and_intake_is_idempotent(): void
    {
        [$record, $cdrVersion, $japicVersion] = $this->eligibleRecord();
        $eligibility = app(PswdoEligibilityService::class);
        $intake = app(PswdoEnrollmentIntakeService::class);

        $this->assertTrue($eligibility->isEligible($record->fresh()));
        $first = DB::transaction(fn () => $intake->receiveEligible($record->id));
        $second = DB::transaction(fn () => $intake->receiveEligible($record->id));
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('pswdo_enrollments', 1);

        $record->cdrProcessing->update(['current_final_version_id' => null]);
        $this->assertFalse($eligibility->isEligible($record->fresh()));
        $record->cdrProcessing->update(['current_final_version_id' => $cdrVersion->id]);
        $record->japicCertificationProcessing->forceFill(['current_final_version_id' => null])->save();
        $this->assertFalse($eligibility->isEligible($record->fresh()));
        $record->japicCertificationProcessing->forceFill(['current_final_version_id' => $japicVersion->id])->save();
        $this->assertTrue($eligibility->isEligible($record->fresh()));
    }

    public function test_invalid_cross_processing_final_ownership_is_rejected(): void
    {
        [$record] = $this->eligibleRecord();
        [$other, $otherCdrVersion, $otherJapicVersion] = $this->eligibleRecord();
        $eligibility = app(PswdoEligibilityService::class);

        foreach ([
            fn () => $record->cdrProcessing->update(['current_final_version_id' => $otherCdrVersion->id]),
            fn () => $record->japicCertificationProcessing->forceFill(['current_final_version_id' => $otherJapicVersion->id])->save(),
        ] as $attack) {
            try {
                $attack();
                $this->fail('A cross-processing final ownership substitution was accepted.');
            } catch (\Throwable $exception) {
                $this->assertMatchesRegularExpression('/belongs to another|Invalid JAPIC current document/', $exception->getMessage());
            }
        }
        $this->assertTrue($eligibility->isEligible($record->fresh()));
        $this->assertNotSame($record->id, $other->id);
    }

    public function test_eligible_get_requests_never_create_enrollment_records(): void
    {
        $this->eligibleRecord();
        $pswdo = User::factory()->role('pswdo')->create();
        $this->assertDatabaseCount('pswdo_enrollments', 0);

        $this->actingAs($pswdo)->get(route('pswdo.dashboard'))->assertOk();
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.index'))->assertOk();
        $this->assertDatabaseCount('pswdo_enrollments', 0);
    }

    public function test_japic_completion_automatically_creates_one_enrollment_in_the_same_flow(): void
    {
        Storage::fake('local');
        [$record, , $japicFinal] = $this->eligibleRecord();
        $processing = $record->japicCertificationProcessing;
        DB::table('japic_certification_processings')->where('id', $processing->id)->update([
            'status' => JapicCertificationStatus::Pending->value, 'current_final_version_id' => null,
            'completed_at' => null, 'completed_by' => null, 'lock_version' => 0,
        ]);
        DB::table('japic_certification_document_versions')->where('id', $japicFinal->id)->delete();
        $actor = User::query()->findOrFail($processing->completed_by ?? $japicFinal->uploaded_by);

        app(JapicCertificationDocumentService::class)->uploadFinal(
            $processing->fresh(), UploadedFile::fake()->createWithContent('signed.pdf', "%PDF-1.4\nsigned\n%%EOF"),
            0, 0, true, true, $actor, null, null,
        );
        $this->assertDatabaseHas('pswdo_enrollments', ['ib39_surfaced_former_rebel_id' => $record->id]);
        $this->assertDatabaseCount('pswdo_enrollments', 1);
    }

    /** @return array{Ib39SurfacedFormerRebel, mixed, JapicCertificationDocumentVersion} */
    private function eligibleRecord(bool $firearms = true): array
    {
        $ib39 = User::factory()->role('39th_ib')->create();
        $japic = User::factory()->role('japic')->create();
        $municipality = Municipality::query()->create(['name' => 'Eligibility '.uniqid()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Eligible', 'last_name' => uniqid(), 'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => $firearms,
        ], $ib39)->load('cdrProcessing');
        $cdr = $record->cdrProcessing;
        $cdrVersion = $cdr->documentVersions()->create([
            'version_number' => 1, 'source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final.pdf",
            'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20,
            'sha256' => hash('sha256', uniqid('cdr', true)), 'created_by' => $ib39->id, 'finalized_at' => now(),
        ]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id, 'current_final_version_id' => $cdrVersion->id]);
        $processing = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id, 'triggering_cdr_document_version_id' => $cdrVersion->id,
            'status' => JapicCertificationStatus::Completed, 'received_at' => now(), 'due_at' => now()->addDays(14),
            'completed_at' => now(), 'completed_by' => $japic->id, 'lock_version' => 1,
        ]);
        $japicVersion = $processing->documentVersions()->create([
            'version_number' => 1, 'storage_path' => "japic/certifications/{$processing->id}/final-documents/final.pdf",
            'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20,
            'sha256' => hash('sha256', uniqid('japic', true)), 'uploaded_by' => $japic->id,
            'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $processing->forceFill(['current_final_version_id' => $japicVersion->id])->save();

        return [$record->fresh(), $cdrVersion, $japicVersion];
    }
}
