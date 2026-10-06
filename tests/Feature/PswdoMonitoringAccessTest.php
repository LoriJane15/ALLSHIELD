<?php

namespace Tests\Feature;

use App\Enums\Ib39FeaDocumentType;
use App\Enums\Ib39FrCategory;
use App\Models\FormerRebel;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PswdoMonitoringAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_pswdo_can_monitor_only_authorized_private_records_without_name_based_assistance_inference(): void
    {
        Storage::fake('local');
        [$pswdo, $enrollment, $cdr, $cdrFinal, $japic, $japicFinal] = $this->context();
        Storage::disk('local')->put($cdrFinal->getRawOriginal('storage_path'), "%PDF-1.4\ncdr\n%%EOF");
        Storage::disk('local')->put($japicFinal->getRawOriginal('storage_path'), "%PDF-1.4\njapic\n%%EOF");

        $unrelated = FormerRebel::query()->create(['classified_id' => 'UNRELATED-1', 'firstname' => 'Monitor', 'lastname' => 'Subject']);
        $unrelated->assistances()->create(['assistance_type' => 'Must not be inferred', 'status' => 'Completed']);

        $this->actingAs($pswdo)->get(route('pswdo.enrollments.records.cdr', $enrollment))
            ->assertRedirect(route('cdr.workspace', $cdr));
        $this->actingAs($pswdo)->get(route('cdr.workspace', $cdr))
            ->assertOk()
            ->assertSee(route('cdr.documents.preview', ['cdr' => $cdr, 'file' => 1]), false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History')
            ->assertDontSee('Download CDR');
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.records.certification', $enrollment))
            ->assertRedirect(route('japic.certifications.workspace', $japic));
        $this->actingAs($pswdo)->get(route('japic.certifications.workspace', $japic))
            ->assertOk()
            ->assertSee(route('japic.certifications.document-versions.preview', [$japic, $japicFinal, 'file' => 1]), false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History');
        $this->actingAs($pswdo)->get(route('pswdo.enrollments.records.assistance', $enrollment))->assertOk()
            ->assertSee('No documents available')->assertDontSee('Must not be inferred');

        foreach ([
            route('pswdo.cdr.documents.preview', [$cdr, $cdrFinal]),
            route('pswdo.japic.document-versions.preview', [$japic, $japicFinal]),
            route('pswdo.japic.document-versions.download', [$japic, $japicFinal]),
        ] as $url) {
            $this->actingAs($pswdo)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        }
        $this->actingAs($pswdo)->get(route('pswdo.cdr.documents.download', [$cdr, $cdrFinal]))->assertForbidden();
    }

    public function test_fea_monitoring_is_read_only_and_parent_substitution_is_denied(): void
    {
        Storage::fake('local');
        [$pswdo, $enrollment] = $this->context();
        $fea = $enrollment->surfacedFormerRebel->feaProcessing;
        $document = $fea->documents()->where('document_type', Ib39FeaDocumentType::Tir)->firstOrFail();
        $path = "ib39/fea/{$fea->id}/finals/monitor.pdf";
        $version = $document->versions()->create(['fea_processing_id' => $fea->id, 'slot' => 'final_primary', 'version_number' => 1, 'storage_path' => $path, 'original_filename' => 'monitor.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => str_repeat('f', 64), 'uploaded_by' => User::factory()->role('39th_ib')->create()->id]);
        $document->update(['status' => 'Completed', 'current_final_version_id' => $version->id, 'completed_at' => now()]);
        Storage::disk('local')->put($path, "%PDF-1.4\nfea\n%%EOF");

        $this->actingAs($pswdo)->get(route('pswdo.enrollments.records.fea', $enrollment))
            ->assertOk()->assertSee('View')
            ->assertSee('href="'.route('fea.documents.view', [$fea, $document]).'"', false);
        $this->actingAs($pswdo)->get(route('fea.documents.view', [$fea, $document]))
            ->assertOk()
            ->assertSee(route('pswdo.fea.documents.versions.preview', [$fea, $document, $version]), false)
            ->assertSee('Comments &amp; Remarks', false)
            ->assertSee('Document History');
        $this->actingAs($pswdo)->get(route('pswdo.fea.documents.versions.preview', [$fea, $document, $version]))
            ->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        [, $other] = $this->context();
        $otherFea = $other->surfacedFormerRebel->feaProcessing;
        $this->actingAs($pswdo)->get(route('pswdo.fea.documents.versions.preview', [$otherFea, $document, $version]))->assertNotFound();
        $this->actingAs($pswdo)->post(route('ib39.fea.documents.start', [$fea, $document]))->assertForbidden();
        $this->actingAs($pswdo)->get(route('ib39.fr-profiles.create'))->assertForbidden();
        $this->actingAs($pswdo)->get(route('admin.rcsp.index'))->assertForbidden();
    }

    private function context(): array
    {
        $pswdo = User::factory()->role('pswdo')->create();
        $ib39 = User::factory()->role('39th_ib')->create();
        $japicUser = User::factory()->role('japic')->create();
        $municipality = Municipality::query()->create(['name' => 'Monitoring '.uniqid()]);
        $record = app(Ib39SurfacedFormerRebelService::class)->create(['first_name' => 'Monitor', 'last_name' => 'Subject', 'category' => Ib39FrCategory::RegularMember->value, 'municipality_id' => $municipality->id, 'surfaced_at' => '2026-09-01', 'possessed_firearms' => true], $ib39)->load(['cdrProcessing', 'feaProcessing.documents']);
        $cdr = $record->cdrProcessing;
        $cdrFinal = $cdr->finalDocument()->create(['source_type' => 'uploaded', 'storage_path' => "ib39/cdr/{$cdr->id}/final-documents/final.pdf", 'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'created_by' => $ib39->id, 'finalized_at' => now()]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id]);
        $japic = JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $record->id, 'triggering_cdr_final_document_id' => $cdrFinal->id, 'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14), 'completed_at' => now(), 'completed_by' => $japicUser->id, 'lock_version' => 1]);
        $japicFinal = $japic->documentVersions()->create(['version_number' => 1, 'storage_path' => "japic/certifications/{$japic->id}/final-documents/final.pdf", 'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 20, 'sha256' => hash('sha256', uniqid()), 'uploaded_by' => $japicUser->id, 'all_signatories_confirmed' => true, 'correct_final_confirmed' => true, 'uploaded_at' => now()]);
        $japic->forceFill(['current_final_version_id' => $japicFinal->id])->save();
        $enrollment = DB::transaction(fn () => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));
        $enrollment->setRelation('surfacedFormerRebel', $record);

        return [$pswdo, $enrollment, $cdr, $cdrFinal, $japic, $japicFinal];
    }
}
