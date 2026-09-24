<?php

namespace Tests\Feature;

use App\Enums\Ib39CdrStatus;
use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39ReferenceSequence;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39CdrProcessingService;
use App\Services\Ib39CdrStatusService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Ib39CdrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Municipality $municipality;

    private Barangay $barangay;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->municipality = Municipality::query()->create(['name' => 'Synthetic Stage 3 Municipality']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Synthetic Stage 3 Barangay',
        ]);
        $this->actor = User::factory()->role('39th_ib')->create();
    }

    public function test_new_surfaced_fr_atomically_receives_one_pending_cdr_form_and_safe_actor_history(): void
    {
        $record = $this->createThroughService();
        $processing = $record->cdrProcessing()->with(['form', 'statusHistories'])->sole();
        $history = $processing->statusHistories->sole();

        $this->assertSame(Ib39CdrStatus::Pending, $processing->status);
        $this->assertNull($processing->started_at);
        $this->assertNull($processing->due_at);
        $this->assertNull($processing->completed_at);
        $this->assertSame(1, $processing->form->schema_version);
        $this->assertNull($processing->form->content);
        $this->assertSame($this->actor->id, $history->user_id);
        $this->assertSame('surfaced_fr_created', $history->event);
        $this->assertNull($history->from_status);
        $this->assertSame(Ib39CdrStatus::Pending, $history->to_status);
        $this->assertNull($history->remarks);
        $this->assertNull($history->delay_reason);

        $serializedHistory = json_encode($history->attributesToArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('SensitiveFirst', $serializedHistory);
        $this->assertStringNotContainsString('SensitiveLast', $serializedHistory);
        $this->assertStringNotContainsString('Sensitive initial operational remarks', $serializedHistory);
    }

    public function test_idempotent_creation_retry_does_not_duplicate_cdr_form_or_initial_history(): void
    {
        $record = $this->standaloneRecord();
        $service = app(Ib39CdrProcessingService::class);

        $first = $service->ensureForSurfacedFormerRebel($record, $this->actor);
        $second = $service->ensureForSurfacedFormerRebel($record, $this->actor);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $record->cdrProcessing()->count());
        $this->assertSame(1, $first->form()->count());
        $this->assertSame(1, $first->statusHistories()->where('event', 'surfaced_fr_created')->count());
    }

    public function test_cdr_history_failure_rolls_back_fr_reference_cdr_form_history_and_audit(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_stage3_initial_cdr_history
            BEFORE INSERT ON ib39_cdr_status_histories
            WHEN NEW.event = 'surfaced_fr_created'
            BEGIN
                SELECT RAISE(ABORT, 'simulated CDR history failure');
            END;
        SQL);

        try {
            $this->createThroughService();
            $this->fail('The simulated CDR history failure did not abort creation.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated CDR history failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 0);
        $this->assertDatabaseCount('ib39_cdr_processings', 0);
        $this->assertDatabaseCount('ib39_cdr_forms', 0);
        $this->assertDatabaseCount('ib39_cdr_status_histories', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(0, Ib39ReferenceSequence::query()->firstOrFail()->current_value);
    }

    public function test_pending_can_start_once_and_repeated_start_or_draft_save_is_idempotent(): void
    {
        $processing = $this->createThroughService()->cdrProcessing;
        $statuses = app(Ib39CdrStatusService::class);

        $this->travelTo(now()->startOfSecond());
        $started = $statuses->start($processing, $this->actor, '127.0.0.1', 'Synthetic test agent');
        $startedAt = $started->started_at;

        $this->assertSame(Ib39CdrStatus::Ongoing, $started->status);
        $this->assertNotNull($startedAt);
        $this->assertNull($started->due_at);
        $this->assertSame(2, $started->statusHistories()->count());
        $this->assertDatabaseHas('ib39_cdr_status_histories', [
            'cdr_processing_id' => $started->id,
            'user_id' => $this->actor->id,
            'from_status' => Ib39CdrStatus::Pending->value,
            'to_status' => Ib39CdrStatus::Ongoing->value,
            'event' => 'processing_started',
        ]);

        $this->travel(10)->minutes();
        $statuses->start($started, $this->actor);
        $laterDraft = $statuses->recordDraftSaved($started, $this->actor);

        $this->assertTrue($startedAt->equalTo($laterDraft->started_at));
        $this->assertSame(2, $laterDraft->statusHistories()->count());
    }

    public function test_first_draft_save_moves_pending_to_ongoing_without_a_manual_status_input(): void
    {
        $processing = $this->createThroughService()->cdrProcessing;

        $ongoing = app(Ib39CdrStatusService::class)->recordDraftSaved($processing, $this->actor);

        $this->assertSame(Ib39CdrStatus::Ongoing, $ongoing->status);
        $this->assertNotNull($ongoing->started_at);
        $this->assertSame('draft_processing_started', $ongoing->statusHistories()->latest('id')->value('event'));
    }

    public function test_completed_cdr_rejects_start_and_draft_transitions(): void
    {
        $processing = $this->createThroughService()->cdrProcessing;
        $processing->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $this->actor->id,
        ]);
        $statuses = app(Ib39CdrStatusService::class);

        foreach (['start', 'recordDraftSaved'] as $method) {
            try {
                $statuses->{$method}($processing, $this->actor);
                $this->fail("Completed CDR accepted {$method}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('cdr', $exception->errors());
            }
        }

        $this->assertSame(Ib39CdrStatus::Completed, $processing->fresh()->status);
        $this->assertSame(1, $processing->statusHistories()->count());
    }

    public function test_profile_and_list_display_real_status_mapping_and_safe_legacy_fallback(): void
    {
        $pending = $this->createThroughService();
        $ongoing = $this->createThroughService();
        $ongoing->cdrProcessing->update(['status' => Ib39CdrStatus::Ongoing, 'started_at' => now()]);
        $completed = $this->createThroughService();
        $completed->cdrProcessing->update([
            'status' => Ib39CdrStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $this->actor->id,
        ]);
        $legacy = $this->standaloneRecord();

        $this->actingAs($this->actor)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk()
            ->assertDontSee('CDR Status')
            ->assertSee('CDR Ongoing')
            ->assertSee('CDR Completed')
            ->assertSee('Newly Recorded');

        $this->actingAs($this->actor)
            ->get(route('ib39.fr-profiles.show', $ongoing))
            ->assertOk()
            ->assertSeeInOrder(['CDR status', 'Ongoing', 'Overall case status', 'CDR Ongoing']);

        $this->actingAs($this->actor)
            ->get(route('ib39.fr-profiles.show', $legacy))
            ->assertOk()
            ->assertSeeInOrder(['CDR status', 'Not Available', 'Overall case status', 'Newly Recorded']);

        $this->assertNotNull($pending->cdrProcessing);
    }

    public function test_list_eager_loads_all_cdr_statuses_with_one_query(): void
    {
        for ($index = 0; $index < 10; $index++) {
            $this->createThroughService();
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'ib39_cdr_processings')) {
                $queries[] = $query->sql;
            }
        });

        $this->actingAs($this->actor)
            ->get(route('ib39.fr-profiles.index'))
            ->assertOk();

        $this->assertCount(1, $queries);
        $this->assertStringContainsString(' in (', strtolower($queries[0]));
    }

    public function test_creation_does_not_create_other_workflow_or_messaging_records(): void
    {
        $tables = [
            'fr_government_assistances',
        ];
        $before = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->createThroughService();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "Unexpected {$table} record was created.");
        }
    }

    private function createThroughService(): Ib39SurfacedFormerRebel
    {
        return app(Ib39SurfacedFormerRebelService::class)->create([
            'first_name' => 'SensitiveFirst',
            'last_name' => 'SensitiveLast',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->barangay->id,
            'surfaced_at' => '2026-08-31',
            'possessed_firearms' => false,
            'initial_remarks' => 'Sensitive initial operational remarks',
        ], $this->actor, '127.0.0.1', 'Synthetic test agent');
    }

    private function standaloneRecord(): Ib39SurfacedFormerRebel
    {
        return Ib39SurfacedFormerRebel::query()->forceCreate([
            'reference_number' => 'FR-LEGACY-'.strtoupper(uniqid()),
            'first_name' => 'Synthetic',
            'last_name' => 'Legacy',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->barangay->id,
            'surfaced_at' => '2026-08-31',
            'possessed_firearms' => false,
            'created_by' => $this->actor->id,
        ]);
    }
}
