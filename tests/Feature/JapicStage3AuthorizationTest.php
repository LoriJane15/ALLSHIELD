<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Support\JapicCertificationDraftSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JapicStage3AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_inventory_has_only_the_six_approved_stage_three_routes_and_methods(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_starts_with($route->getName() ?? '', 'japic.'));
        $this->assertCount(25, $routes);
        $this->assertCount(20, $routes->filter(fn ($route) => $route->methods() === ['GET', 'HEAD']));
        $this->assertCount(1, $routes->filter(fn ($route) => $route->methods() === ['PUT']));
        $this->assertCount(4, $routes->filter(fn ($route) => $route->methods() === ['POST']));
        $this->assertSame([], $routes->filter(fn ($route) => array_intersect($route->methods(), ['DELETE', 'PATCH']))->values()->all());
        foreach (['japic.certifications.records.cdr', 'japic.certifications.records.fea', 'japic.certifications.records.assistance', 'japic.certifications.records.certification', 'japic.certifications.history',
            'japic.certifications.document-versions.preview', 'japic.certifications.document-versions.download',
            'japic.pswdo-enrollment-documents.preview', 'japic.pswdo-enrollment-documents.download'] as $name) {
            $this->assertSame(['GET', 'HEAD'], $routes->first(fn ($route) => $route->getName() === $name)->methods());
        }
    }

    public function test_other_roles_and_other_assigned_japic_users_cannot_use_stage_three_routes(): void
    {
        [$processing, $owner] = $this->processing();
        $other = User::factory()->role('japic')->create();
        $processing->forceFill(['assigned_to' => $owner->id])->save();
        foreach (['39th_ib', 'admin', 'super_admin', 'lgu', 'afp', 'mblrc', 'gov_agency'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('japic.certifications.draft.edit', $processing))->assertForbidden();
        }
        $this->actingAs($other)->get(route('japic.certifications.draft.edit', $processing))->assertForbidden();
        $this->actingAs($other)->put(route('japic.certifications.draft.update', $processing), [])->assertForbidden();
        foreach (['japic.certifications.records.cdr', 'japic.certifications.records.fea', 'japic.certifications.records.assistance', 'japic.certifications.records.certification', 'japic.certifications.history'] as $route) {
            $this->actingAs($other)->get(route($route, $processing))->assertForbidden();
        }
    }

    public function test_editing_is_locked_after_for_signing(): void
    {
        [$processing, $owner] = $this->processing();
        $processing->forceFill(['status' => JapicCertificationStatus::ForSigning])->save();
        $this->actingAs($owner)->get(route('japic.certifications.draft.edit', $processing))->assertForbidden();
        $this->actingAs($owner)->put(route('japic.certifications.draft.update', $processing), [])->assertForbidden();
        $this->actingAs($owner)->post(route('japic.certifications.submit-for-signing', $processing), [])->assertForbidden();
    }

    public function test_cancelled_draft_remains_previewable_but_cannot_be_mutated(): void
    {
        [$processing, $owner] = $this->processing();
        $source = app(JapicCertificationDraftSchema::class)->sourceSnapshot($processing);
        $payload = app(JapicCertificationDraftSchema::class)->normalize([], $source, null, null);
        $processing->draft()->forceCreate(['payload' => $payload, 'schema_version' => 2, 'revision' => 1, 'last_saved_by' => $owner->id, 'last_saved_at' => now()]);
        DB::table('ib39_fr_cancellations')->insert(['ib39_surfaced_former_rebel_id' => $processing->ib39_surfaced_former_rebel_id,
            'previous_overall_status' => 'CDR Completed', 'reason' => encrypt('Cancelled'), 'cancelled_by' => $owner->id,
            'cancelled_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $processing->forceFill(['status' => JapicCertificationStatus::Cancelled])->save();

        $this->actingAs($owner)->get(route('japic.certifications.preview', $processing))->assertOk();
        $this->actingAs($owner)->put(route('japic.certifications.draft.update', $processing), [])->assertForbidden();
        $this->actingAs($owner)->post(route('japic.certifications.signing-complete', $processing), [])->assertForbidden();
    }

    private function processing(): array
    {
        $owner = User::factory()->role('japic')->create();
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Auth City', 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'FR-AUTH', 'first_name' => 'Auth', 'last_name' => 'Subject', 'category' => 'Regular Member',
            'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-08-01', 'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/auth', 'original_filename' => 'auth.html',
            'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('e', 64), 'content_schema_version' => 2, 'content_snapshot' => ['content' => []], 'created_by' => $actor->id, 'finalized_at' => now()]);

        return [JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr, 'triggering_cdr_document_version_id' => $version->id,
            'status' => JapicCertificationStatus::Pending, 'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]), $owner];
    }
}
