<?php

namespace Tests\Feature;

use App\Enums\JapicCertificationStatus;
use App\Models\Ib39CdrDocumentVersion;
use App\Models\JapicCertificationProcessing;
use App\Models\User;
use App\Services\JapicCertificationPhotoService;
use App\Support\JapicCertificationUploadedFile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class JapicCertificationPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_upload_and_replacement_create_immutable_versions_and_selected_draft_revisions(): void
    {
        Storage::fake('local');
        [$processing, $japic] = $this->processing();

        $this->actingAs($japic)->post(route('japic.certifications.photos.store', $processing), [
            'revision' => 0, 'lock_version' => 0, 'photo' => UploadedFile::fake()->image('first.png', 640, 480),
        ])->assertRedirect(route('japic.certifications.draft.edit', $processing));

        $processing->refresh();
        $first = $processing->currentPhotoVersion;
        $this->assertSame(1, $first->version_number);
        $this->assertSame(1, $processing->draft->revision);
        $this->assertSame(2, $processing->draft->schema_version);
        $this->assertSame($first->id, data_get($processing->draft->payload, 'certificate.photo_version_id'));
        $this->assertStringStartsWith("japic/certifications/{$processing->id}/photos/", $first->getRawOriginal('storage_path'));
        $this->assertNotSame('first.png', basename($first->getRawOriginal('storage_path')));
        Storage::disk('local')->assertExists($first->getRawOriginal('storage_path'));

        $this->actingAs($japic)->post(route('japic.certifications.photos.store', $processing), [
            'revision' => 1, 'lock_version' => 1, 'photo' => UploadedFile::fake()->image('replacement.jpg', 800, 600),
        ])->assertRedirect();
        $processing->refresh();
        $second = $processing->currentPhotoVersion;
        $this->assertSame(2, $second->version_number);
        $this->assertSame($first->id, $second->replaces_version_id);
        $this->assertDatabaseCount('japic_certification_photo_versions', 2);
        $this->assertDatabaseCount('japic_certification_draft_histories', 2);
        Storage::disk('local')->assertExists($first->getRawOriginal('storage_path'));
        Storage::disk('local')->assertExists($second->getRawOriginal('storage_path'));

        try {
            DB::table('japic_certification_photo_versions')->where('id', $first->id)->update(['width' => 1]);
            $this->fail('A photo version was updated outside Eloquent.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::table('japic_certification_photo_versions')->where('id', $first->id)->delete();
            $this->fail('A photo version was deleted outside Eloquent.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_upload_validation_checks_content_and_leaves_no_database_or_storage_artifacts(): void
    {
        Storage::fake('local');
        [$processing, $japic] = $this->processing();
        $bad = UploadedFile::fake()->createWithContent('forged.png', '%PDF-not-an-image');

        $this->actingAs($japic)->from(route('japic.certifications.draft.edit', $processing))
            ->post(route('japic.certifications.photos.store', $processing), [
                'revision' => 0, 'lock_version' => 0, 'photo' => $bad,
            ])->assertSessionHasErrors('photo');

        $this->assertDatabaseCount('japic_certification_photo_versions', 0);
        $this->assertDatabaseCount('japic_certification_drafts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->expectException(ValidationException::class);
        JapicCertificationUploadedFile::inspect(UploadedFile::fake()->createWithContent('forged.jpg', "\x89PNG\r\n\x1a\ninvalid"));
    }

    public function test_private_inline_access_enforces_role_assignment_and_nested_record_ownership(): void
    {
        Storage::fake('local');
        [$first, $owner] = $this->processing('One');
        [$second] = $this->processing('Two');
        $photo = app(JapicCertificationPhotoService::class)->store($first, UploadedFile::fake()->image('private.png', 300, 300), 0, 0, $owner);

        $this->actingAs($owner)->get(route('japic.certifications.photos.show', [$first, $photo]))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($owner)->get(route('japic.certifications.photos.show', [$second, $photo]))->assertNotFound();
        $this->actingAs(User::factory()->role('39th_ib')->create())
            ->get(route('japic.certifications.photos.show', [$first, $photo]))->assertForbidden();

        $other = User::factory()->role('japic')->create();
        $first->forceFill(['assigned_to' => $owner->id])->save();
        $this->actingAs($other)->get(route('japic.certifications.photos.show', [$first, $photo]))->assertForbidden();
        $this->actingAs($other)->post(route('japic.certifications.photos.store', $first), [
            'revision' => 1, 'lock_version' => 1, 'photo' => UploadedFile::fake()->image('denied.png'),
        ])->assertForbidden();
    }

    public function test_stale_frozen_cancelled_and_failed_database_saves_leave_no_orphan_files(): void
    {
        Storage::fake('local');
        [$processing, $japic] = $this->processing();
        $service = app(JapicCertificationPhotoService::class);
        $service->store($processing, UploadedFile::fake()->image('one.png', 200, 200), 0, 0, $japic);
        $files = Storage::disk('local')->allFiles();

        try {
            $service->store($processing->fresh(), UploadedFile::fake()->image('stale.png', 200, 200), 0, 0, $japic);
            $this->fail('Stale photo upload was accepted.');
        } catch (ConflictHttpException) {
            $this->addToAssertionCount(1);
        }
        $processing->forceFill(['status' => JapicCertificationStatus::ForSigning])->save();
        $this->actingAs($japic)->post(route('japic.certifications.photos.store', $processing), [
            'revision' => 1, 'lock_version' => 1, 'photo' => UploadedFile::fake()->image('frozen.png'),
        ])->assertForbidden();
        $this->assertSame($files, Storage::disk('local')->allFiles());

        [$failure, $failureUser] = $this->processing('Failure');
        DB::unprepared("CREATE TRIGGER japic_test_photo_failure BEFORE INSERT ON japic_certification_draft_histories BEGIN SELECT RAISE(ABORT, 'forced draft history failure'); END");
        try {
            $service->store($failure, UploadedFile::fake()->image('failure.png', 200, 200), 0, 0, $failureUser);
            $this->fail('Forced database failure did not fail.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseMissing('japic_certification_photo_versions', ['processing_id' => $failure->id]);
        $this->assertSame($files, Storage::disk('local')->allFiles());
    }

    public function test_database_rejects_cross_processing_replacement_and_current_pointer_substitution(): void
    {
        Storage::fake('local');
        [$first, $owner] = $this->processing('One');
        [$second] = $this->processing('Two');
        $photo = app(JapicCertificationPhotoService::class)->store($first, UploadedFile::fake()->image('one.png', 200, 200), 0, 0, $owner);

        foreach ([
            fn () => DB::table('japic_certification_processings')->where('id', $second->id)->update(['current_photo_version_id' => $photo->id]),
            fn () => DB::table('japic_certification_photo_versions')->insert([
                'processing_id' => $second->id, 'version_number' => 1, 'replaces_version_id' => $photo->id,
                'storage_path' => 'japic/certifications/x/photos/x.png', 'original_filename' => 'x.png',
                'mime_type' => 'image/png', 'size_bytes' => 1, 'width' => 100, 'height' => 100,
                'sha256' => str_repeat('a', 64), 'uploaded_by' => $owner->id, 'uploaded_at' => now(),
            ]),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Cross-processing photo substitution was accepted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function processing(string $suffix = 'Subject'): array
    {
        $japic = User::factory()->role('japic')->create();
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = DB::table('municipalities')->insertGetId(['name' => 'Photo Municipality '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
        $fr = DB::table('ib39_surfaced_former_rebels')->insertGetId(['reference_number' => 'PHOTO-'.uniqid(), 'first_name' => 'Photo', 'last_name' => $suffix,
            'category' => 'Regular Member', 'province' => 'Davao del Sur', 'municipality_id' => $municipality, 'surfaced_at' => '2026-08-01',
            'possessed_firearms' => 0, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $cdr = DB::table('ib39_cdr_processings')->insertGetId(['ib39_surfaced_former_rebel_id' => $fr, 'status' => 'Completed', 'completed_at' => now(), 'completed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $version = Ib39CdrDocumentVersion::query()->forceCreate(['cdr_processing_id' => $cdr, 'version_number' => 1, 'source_type' => 'generated', 'storage_path' => 'generated/photo',
            'original_filename' => 'photo.html', 'mime_type' => 'text/html', 'size_bytes' => 1, 'sha256' => str_repeat('b', 64), 'content_schema_version' => 2,
            'content_snapshot' => ['content' => ['gender' => 'Female'], 'fr_photo_version_id' => null], 'created_by' => $actor->id, 'finalized_at' => now()]);
        DB::table('ib39_cdr_processings')->where('id', $cdr)->update(['current_final_version_id' => $version->id]);

        return [JapicCertificationProcessing::query()->forceCreate(['ib39_surfaced_former_rebel_id' => $fr,
            'triggering_cdr_document_version_id' => $version->id, 'status' => JapicCertificationStatus::Pending,
            'received_at' => now(), 'due_at' => now()->addDays(14), 'lock_version' => 0]), $japic];
    }
}
