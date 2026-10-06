<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\JapicCertificationProcessing;
use App\Models\Municipality;
use App\Models\PswdoEnrollment;
use App\Models\User;
use App\Services\Ib39SurfacedFormerRebelService;
use App\Services\PswdoEnrollmentIntakeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessingWorkspaceCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_three_process_specific_comment_tables_are_added(): void
    {
        foreach ([
            'japic_certification_comments' => ['japic_certification_processing_id', 'japic_certification_processings'],
            'ib39_fea_processing_comments' => ['ib39_fea_processing_id', 'ib39_fea_processings'],
            'pswdo_enrollment_comments' => ['pswdo_enrollment_id', 'pswdo_enrollments'],
        ] as $table => [$parentKey, $parentTable]) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(
                ['id', $parentKey, 'user_id', 'author_role', 'text', 'created_at', 'updated_at'],
                Schema::getColumnListing($table),
            );
            $foreignKeys = collect(DB::select('PRAGMA foreign_key_list("'.$table.'")'));
            $this->assertEqualsCanonicalizing([$parentTable, 'users'], $foreignKeys->pluck('table')->all());
            $this->assertTrue($foreignKeys->every(fn ($key): bool => $key->on_delete === 'RESTRICT'));
            $this->assertContains($table.'_chronological_index', collect(DB::select('PRAGMA index_list("'.$table.'")'))->pluck('name')->all());
        }
    }

    public function test_japic_fea_and_pswdo_workspaces_keep_separate_shared_comment_threads(): void
    {
        [$japic, $fea, $enrollment] = $this->processes();
        $unrelated = User::factory()->role('lgu')->create();
        $contexts = [
            [$japic, 'japic.certifications.workspace', 'japic.certifications.comments.store', 'japic_certification_comments',
                User::factory()->role('japic')->create(['name' => 'JAPIC Reviewer']), User::factory()->role('japic')->create(['name' => 'JAPIC Colleague'])],
            [$fea, 'ib39.fea.show', 'fea.comments.store', 'ib39_fea_processing_comments',
                User::factory()->role('39th_ib')->create(['name' => 'FEA Processor']), User::factory()->role('39th_ib')->create(['name' => 'FEA Colleague'])],
            [$enrollment, 'pswdo.enrollments.workspace', 'pswdo.enrollments.comments.store', 'pswdo_enrollment_comments',
                User::factory()->role('pswdo')->create(['name' => 'PSWDO Processor']), User::factory()->role('pswdo')->create(['name' => 'PSWDO Colleague'])],
        ];

        foreach ($contexts as [$process, $workspaceRoute, $commentRoute, $table, $processor, $viewer]) {
            $workspace = route($workspaceRoute, $process);
            $post = route($commentRoute, $process);
            $this->actingAs($processor)->from($workspace)->post($post, [
                'text' => '  Processor note for '.$table.'  ', 'author_role' => 'super_admin',
            ])->assertRedirect($workspace);
            $this->actingAs($viewer)->get($workspace)->assertOk()
                ->assertSee('Comments &amp; Remarks', false)
                ->assertSee('Document History')
                ->assertSee('Processor note for '.$table)
                ->assertSee($processor->name)
                ->assertSee($processor->role === '39th_ib' ? '39th IB' : strtoupper($processor->role))
                ->assertSee('datetime=', false)
                ->assertSee('process-workspace-grid');
            if ($table === 'pswdo_enrollment_comments') {
                $this->actingAs($viewer)->get($workspace)->assertSee('Enrollment Created');
            }

            $this->actingAs($viewer)->from($workspace)->post($post, ['text' => 'Viewer reply for '.$table])
                ->assertRedirect($workspace);
            $this->actingAs($processor)->get($workspace)->assertOk()
                ->assertSeeInOrder(['Processor note for '.$table, 'Viewer reply for '.$table])
                ->assertSee($viewer->name);
            $this->assertSame(
                ['Processor note for '.$table, 'Viewer reply for '.$table],
                $process->comments()->orderBy('created_at')->orderBy('id')->pluck('text')->all(),
            );
            $this->assertDatabaseHas($table, ['user_id' => $processor->id, 'author_role' => $processor->role]);
            $this->assertDatabaseCount($table, 2);

            $this->actingAs($processor)->post($post, ['text' => " \n\t "])->assertSessionHasErrors('text');
            $this->actingAs($processor)->post($post, ['text' => str_repeat('x', 2001)])->assertSessionHasErrors('text');
            $unrelatedWorkspaceResponse = $this->actingAs($unrelated)->get($workspace);
            if ($table === 'pswdo_enrollment_comments') {
                $unrelatedWorkspaceResponse->assertNotFound();
            } else {
                $unrelatedWorkspaceResponse->assertForbidden();
            }
            $this->actingAs($unrelated)->post($post, ['text' => 'Denied'])->assertForbidden();
            $this->assertDatabaseCount($table, 2);
        }

        $this->actingAs($unrelated)->get(route('japic.certifications.draft.edit', $japic))->assertForbidden();
        $this->actingAs($unrelated)->post(route('ib39.fea.documents.start', [$fea, $fea->documents->first()]))->assertForbidden();
        $this->actingAs($unrelated)->post(route('pswdo.enrollments.documents.store', [$enrollment, 'eclip_enrollment_form']))->assertForbidden();
    }

    public function test_comment_author_profile_image_uses_existing_account_image_with_initials_fallback(): void
    {
        Storage::fake('public');
        [$japic] = $this->processes();
        $viewer = User::factory()->role('japic')->create();
        $withPhoto = User::factory()->role('japic')->create([
            'name' => 'Photo Author',
            'logo' => 'avatars/comment-author.jpg',
        ]);
        $withoutPhoto = User::factory()->role('japic')->create([
            'name' => 'No Foto',
            'logo' => null,
        ]);
        Storage::disk('public')->put($withPhoto->logo, 'image-bytes');
        $japic->comments()->createMany([
            ['user_id' => $withPhoto->id, 'author_role' => 'japic', 'text' => 'Comment with image'],
            ['user_id' => $withoutPhoto->id, 'author_role' => 'japic', 'text' => 'Comment with initials'],
        ]);

        $this->actingAs($viewer)->get(route('japic.certifications.workspace', $japic))
            ->assertOk()
            ->assertSee('src="'.$withPhoto->profileImageUrl().'"', false)
            ->assertSee('Comment with image')
            ->assertSee('>NF</span>', false)
            ->assertSee('Comment with initials');
    }

    private function processes(): array
    {
        $ib39 = User::factory()->role('39th_ib')->create();
        $japicUser = User::factory()->role('japic')->create();
        $municipality = Municipality::query()->create(['name' => 'Workspace Comments Municipality']);
        $record = app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'Shared', 'last_name' => 'Workspace',
            'category' => Ib39FrCategory::RegularMember->value,
            'municipality_id' => $municipality->id,
            'surfaced_at' => '2026-10-01',
            'possessed_firearms' => true,
        ], $ib39)->load(['cdrProcessing', 'feaProcessing.documents']);
        $cdr = $record->cdrProcessing;
        $finalCdr = $cdr->finalDocument()->create([
            'source_type' => 'uploaded', 'storage_path' => 'tests/workspaces/cdr.pdf',
            'original_filename' => 'cdr.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 20, 'sha256' => str_repeat('a', 64),
            'created_by' => $ib39->id, 'finalized_at' => now(),
        ]);
        $cdr->update(['status' => 'Completed', 'completed_at' => now(), 'completed_by' => $ib39->id]);
        $japic = JapicCertificationProcessing::query()->forceCreate([
            'ib39_surfaced_former_rebel_id' => $record->id,
            'triggering_cdr_final_document_id' => $finalCdr->id,
            'status' => 'Completed', 'received_at' => now(), 'due_at' => now()->addDays(14),
            'completed_at' => now(), 'completed_by' => $japicUser->id, 'lock_version' => 1,
        ]);
        $final = $japic->documentVersions()->create([
            'version_number' => 1, 'storage_path' => 'tests/workspaces/japic.pdf',
            'original_filename' => 'japic.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 20, 'sha256' => str_repeat('b', 64),
            'uploaded_by' => $japicUser->id, 'all_signatories_confirmed' => true,
            'correct_final_confirmed' => true, 'uploaded_at' => now(),
        ]);
        $japic->forceFill(['current_final_version_id' => $final->id])->save();
        $enrollment = DB::transaction(fn (): ?PswdoEnrollment => app(PswdoEnrollmentIntakeService::class)->receiveEligible($record->id));
        $this->assertNotNull($enrollment);

        return [$japic, $record->feaProcessing, $enrollment];
    }
}
