<?php

namespace Tests\Feature;

use App\Models\{RcspBarangay, User};
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

        $activity = \App\Models\RcspActivity::where('rcsp_phase_id', 1)->firstOrFail();

        $this->actingAs($lgu)
            ->post("/lgu/rcsp/{$bgy->id}/monitoring", [
                'phase_id' => 1,
                "file_{$activity->id}" => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
            ])
            ->assertSessionHasErrors("file_{$activity->id}");
    }
}
