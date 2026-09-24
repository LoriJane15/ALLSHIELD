<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspForm;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RcspEvidenceAccessTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private User $lgu;

    private User $admin;

    private RcspBarangay $record;

    private RcspPhase $phase;

    private RcspActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->municipality = Municipality::create(['name' => 'DEMO Evidence Municipality']);
        $barangay = Barangay::create([
            'municipality_id' => $this->municipality->id,
            'name' => 'DEMO Evidence Barangay',
        ]);
        $this->lgu = User::factory()->lgu($this->municipality->id)->create();
        $this->admin = User::factory()->role('admin')->create();
        $this->phase = RcspPhase::create([
            'number' => 0,
            'name' => 'DEMO Evidence Phase',
            'catalog_key' => 'evidence',
        ]);
        $this->record = RcspBarangay::create([
            'barangay_id' => $barangay->id,
            'municipality_id' => $this->municipality->id,
            'catalog_key' => 'evidence',
        ]);
        $this->activity = RcspActivity::create([
            'rcsp_phase_id' => $this->phase->id,
            'rcsp_barangay_id' => $this->record->id,
            'created_by_user_id' => $this->lgu->id,
            'description' => 'DEMO: Evidence',
            'normalized_title' => 'demo: evidence',
        ]);
    }

    public function test_katuparan_can_preview_pdf_jpeg_and_png_with_same_origin_framing(): void
    {
        $previewable = [
            ['preview.pdf', 'application/pdf', "%PDF-1.4\n%%EOF"],
            ['preview.jpg', 'image/jpeg', "\xFF\xD8\xFF\xD9"],
            ['preview.png', 'image/png', "\x89PNG\r\n\x1A\n"],
        ];

        foreach ($previewable as [$filename, $mime, $contents]) {
            $form = $this->createEvidence($filename, $mime, $contents);

            $this->actingAs($this->admin)->get(route('rcsp.evidence', $form))
                ->assertOk()
                ->assertHeader('Content-Type', $mime)
                ->assertHeader('Content-Disposition', "inline; filename={$filename}")
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertHeader('X-Content-Type-Options', 'nosniff');
        }
    }

    public function test_doc_and_docx_remain_authenticated_attachments_with_framing_denied(): void
    {
        $attachments = [
            ['evidence.doc', 'application/msword', "\xD0\xCF\x11\xE0"],
            ['evidence.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'PK'],
        ];

        foreach ($attachments as [$filename, $mime, $contents]) {
            $form = $this->createEvidence($filename, $mime, $contents);

            $this->actingAs($this->admin)->get(route('rcsp.evidence', $form))
                ->assertOk()
                ->assertHeader('Content-Type', $mime)
                ->assertHeader('Content-Disposition', "attachment; filename={$filename}")
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeaderMissing('Content-Security-Policy');
        }
    }

    public function test_evidence_access_rejects_unauthenticated_unauthorized_and_cross_municipality_users(): void
    {
        $form = $this->createEvidence('authorized.pdf', 'application/pdf', "%PDF-1.4\n%%EOF");

        $this->get(route('rcsp.evidence', $form))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->role('afp')->create())
            ->get(route('rcsp.evidence', $form))->assertForbidden();

        $otherMunicipality = Municipality::create(['name' => 'DEMO Evidence Other']);
        $this->actingAs(User::factory()->lgu($otherMunicipality->id)->create())
            ->get(route('rcsp.evidence', $form))->assertForbidden();
    }

    public function test_submitted_evidence_remains_private_and_keeps_detected_metadata(): void
    {
        $this->actingAs($this->lgu)->post(route('lgu.monitoring.submit', [$this->record, $this->activity]), [
            'conduct' => 'yes',
            'evidence' => UploadedFile::fake()->image('safe.jpg'),
        ])->assertSessionHasNoErrors();

        $form = RcspForm::firstOrFail();
        $this->assertStringStartsWith('private:rcsp/', $form->file);
        $this->assertSame('safe.jpg', $form->original_filename);
        $this->assertSame('image/jpeg', $form->detected_mime_type);
        $this->actingAs($this->lgu)->get(route('rcsp.evidence', $form))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-cache, no-store, private');
    }

    public function test_missing_and_unsafe_paths_return_not_found(): void
    {
        $missing = $this->createEvidence('missing.pdf', 'application/pdf', "%PDF-1.4\n%%EOF");
        Storage::disk('local')->delete($this->evidencePath('missing.pdf'));
        $this->actingAs($this->admin)->get(route('rcsp.evidence', $missing))->assertNotFound();

        $unsafe = $this->createForm('../secret', 'secret.pdf', 'application/pdf');
        $this->actingAs($this->admin)->get(route('rcsp.evidence', $unsafe))->assertNotFound();

        Storage::disk('local')->put('rcsp/999/cross-scope.pdf', '%PDF-1.4');
        $crossScope = $this->createForm('private:rcsp/999/cross-scope.pdf', 'cross-scope.pdf', 'application/pdf');
        $this->actingAs($this->admin)->get(route('rcsp.evidence', $crossScope))->assertNotFound();
    }

    public function test_viewer_url_preserves_non_default_host_and_port_without_hard_coded_loopback(): void
    {
        $form = $this->createEvidence('viewer.pdf', 'application/pdf', "%PDF-1.4\n%%EOF");

        $response = $this->actingAs($this->admin)
            ->get("http://review.test:8123/katuparan/rcsp-form/{$form->id}/file")
            ->assertOk();

        $response->assertSee("http://review.test:8123/rcsp-form/{$form->id}/evidence", false);
        $response->assertDontSee('127.0.0.1', false);
    }

    public function test_normal_non_evidence_response_keeps_global_frame_denial(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    private function createEvidence(string $filename, string $mime, string $contents): RcspForm
    {
        $path = $this->evidencePath($filename);
        Storage::disk('local')->put($path, $contents);

        return $this->createForm('private:'.$path, $filename, $mime);
    }

    private function createForm(string $storedPath, string $filename, string $mime): RcspForm
    {
        return RcspForm::create([
            'lgu_user_id' => $this->lgu->id,
            'rcsp_barangay_id' => $this->record->id,
            'rcsp_phase_id' => $this->phase->id,
            'rcsp_activity_id' => $this->activity->id,
            'submission_version' => RcspForm::where('rcsp_activity_id', $this->activity->id)->count() + 1,
            'conduct' => 'yes',
            'file' => $storedPath,
            'original_filename' => $filename,
            'detected_mime_type' => $mime,
            'file_size_bytes' => 1,
            'status' => 'submitted',
        ]);
    }

    private function evidencePath(string $filename): string
    {
        return "rcsp/{$this->record->id}/{$filename}";
    }
}
