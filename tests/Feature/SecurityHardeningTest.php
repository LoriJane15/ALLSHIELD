<?php

namespace Tests\Feature;

use App\Models\RcspActivity;
use App\Models\RcspBarangay;
use App\Models\RcspPhase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_security_headers_are_sent(): void
    {
        $res = $this->get('/login');

        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_rcsp_monitoring_upload_rejects_disallowed_file_types(): void
    {
        $this->skipUnlessLegacyDataPresent();

        $bgy = RcspBarangay::firstOrFail();
        $lgu = User::where('role', 'lgu')->where('municipality_id', $bgy->municipality_id)->first();

        if (! $lgu) {
            $this->markTestSkipped('No LGU user for this barangay.');
        }

        $phase = RcspPhase::where('catalog_key', $bgy->catalog_key)->where('number', $bgy->current_phase)->firstOrFail();
        $activities = RcspActivity::where('rcsp_phase_id', $phase->id)->get();
        $conduct = $activities->mapWithKeys(fn ($activity) => [$activity->id => 'yes'])->all();
        $activity = $activities->firstOrFail();

        $this->actingAs($lgu)
            ->post("/lgu/rcsp/{$bgy->id}/monitoring", [
                'phase_id' => $phase->id,
                'conduct' => $conduct,
                'evidence' => [$activity->id => UploadedFile::fake()->create('payload.php', 8, 'application/x-php')],
            ])
            ->assertSessionHasErrors("evidence.{$activity->id}");
    }
}
