<?php

namespace Tests\Feature;

use App\Enums\Ib39FrCategory;
use App\Models\Barangay;
use App\Models\Ib39ReferenceSequence;
use App\Models\Ib39SurfacedFormerRebel;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ib39ReferenceSequenceService;
use App\Services\Ib39SurfacedFormerRebelService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Ib39SurfacedFormerRebelDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_has_approved_names_and_no_forwarding_or_identity_cross_references(): void
    {
        $this->assertTrue(Schema::hasTable('ib39_reference_sequences'));
        $this->assertTrue(Schema::hasTable('ib39_surfaced_former_rebels'));

        $this->assertTrue(Schema::hasColumns('ib39_surfaced_former_rebels', [
            'reference_number', 'first_name', 'last_name', 'category',
            'other_category_specification', 'province',
            'municipality_id', 'barangay_id', 'specific_location', 'surfaced_at',
            'possessed_firearms', 'initial_remarks', 'created_by', 'created_at',
            'updated_at', 'deleted_at',
        ]));

        foreach ([
            'forwarded', 'forwarding_organization', 'other_organization_specification',
            'name', 'display_name', 'real_name', 'firstname', 'middlename', 'lastname',
            'alias', 'nickname', 'identity_document_number', 'former_rebel_id',
        ] as $forbiddenColumn) {
            $this->assertFalse(Schema::hasColumn('ib39_surfaced_former_rebels', $forbiddenColumn));
        }

        $indexes = collect(Schema::getIndexes('ib39_surfaced_former_rebels'));
        $this->assertTrue($indexes->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['reference_number']));
    }

    public function test_creation_allocates_server_owned_references_and_relationships(): void
    {
        [$actor, $municipality, $barangay] = $this->domainContext();

        $record = $this->service()->create([
            ...$this->validAttributes($municipality, $barangay),
            'reference_number' => 'FR999',
            'created_by' => User::factory()->role('39th_ib')->create()->id,
        ], $actor);

        $this->assertSame('FR001', $record->reference_number);
        $this->assertSame($actor->id, $record->created_by);
        $this->assertSame('Test First', $record->first_name);
        $this->assertSame('Test Last', $record->last_name);
        $this->assertSame('Test First Test Last', $record->display_name);
        $this->assertSame(Ib39FrCategory::MilisyaNgBayan, $record->category);
        $this->assertFalse($record->possessed_firearms);
        $this->assertSame(Ib39SurfacedFormerRebel::DEFAULT_PROVINCE, $record->province);
        $this->assertTrue($record->municipality->is($municipality));
        $this->assertTrue($record->barangay->is($barangay));
        $this->assertTrue($record->creator->is($actor));
    }

    public function test_sequence_increment_rolls_back_when_record_transaction_fails(): void
    {
        $references = app(Ib39ReferenceSequenceService::class);

        try {
            DB::transaction(function () use ($references) {
                $this->assertSame('FR001', $references->reserveSurfacedFormerRebelReference());

                throw new RuntimeException('Simulated record failure.');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated record failure.', $exception->getMessage());
        }

        $this->assertSame(0, Ib39ReferenceSequence::query()->firstOrFail()->current_value);

        [$actor, $municipality, $barangay] = $this->domainContext();
        $record = $this->service()->create($this->validAttributes($municipality, $barangay), $actor);

        $this->assertSame('FR001', $record->reference_number);
    }

    public function test_soft_deleted_reference_is_never_reused(): void
    {
        [$actor, $municipality, $barangay] = $this->domainContext();
        $first = $this->service()->create($this->validAttributes($municipality, $barangay), $actor);
        $first->delete();

        $second = $this->service()->create($this->validAttributes($municipality, $barangay), $actor);

        $this->assertSame('FR001', $first->reference_number);
        $this->assertSame('FR002', $second->reference_number);
        $this->assertTrue(Ib39SurfacedFormerRebel::withTrashed()->where('reference_number', 'FR001')->exists());
        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 2);
    }

    public function test_reference_format_continues_beyond_three_digits(): void
    {
        Ib39ReferenceSequence::query()
            ->whereKey(Ib39ReferenceSequence::SURFACED_FORMER_REBELS)
            ->update(['current_value' => 999]);

        [$actor, $municipality, $barangay] = $this->domainContext();
        $record = $this->service()->create($this->validAttributes($municipality, $barangay), $actor);

        $this->assertSame('FR1000', $record->reference_number);
    }

    public function test_unique_reference_constraint_is_enforced_for_soft_deleted_rows(): void
    {
        [$actor, $municipality, $barangay] = $this->domainContext();
        $record = $this->service()->create($this->validAttributes($municipality, $barangay), $actor);
        $record->delete();

        $this->expectException(QueryException::class);

        DB::table('ib39_surfaced_former_rebels')->insert([
            ...$this->databaseAttributes($actor, $municipality, $barangay),
            'reference_number' => 'FR001',
        ]);
    }

    public function test_controlled_category_values_are_enforced_by_sqlite(): void
    {
        [$actor, $municipality, $barangay] = $this->domainContext();

        $this->expectException(QueryException::class);

        DB::table('ib39_surfaced_former_rebels')->insert([
            ...$this->databaseAttributes($actor, $municipality, $barangay),
            'reference_number' => 'FR001',
            'category' => 'Uncontrolled Category',
        ]);
    }

    public function test_creation_rejects_wrong_province_and_barangay_from_another_municipality(): void
    {
        [$actor, $municipality, $barangay] = $this->domainContext();

        try {
            $this->service()->create([
                ...$this->validAttributes($municipality, $barangay),
                'province' => 'Another Province',
            ], $actor);
            $this->fail('A province outside Davao del Sur was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('province', $exception->errors());
        }

        $otherMunicipality = Municipality::query()->create(['name' => 'Other Municipality']);

        try {
            $this->service()->create($this->validAttributes($otherMunicipality, $barangay), $actor);
            $this->fail('A barangay outside the selected municipality was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('barangay_id', $exception->errors());
        }

        $this->assertDatabaseCount('ib39_surfaced_former_rebels', 0);
        $this->assertSame(0, Ib39ReferenceSequence::query()->firstOrFail()->current_value);
    }

    public function test_creation_service_rejects_non_39th_ib_actor(): void
    {
        [, $municipality, $barangay] = $this->domainContext();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('');

        $this->service()->create(
            $this->validAttributes($municipality, $barangay),
            User::factory()->role('afp')->create(),
        );
    }

    private function service(): Ib39SurfacedFormerRebelService
    {
        return app(Ib39SurfacedFormerRebelService::class);
    }

    private function domainContext(): array
    {
        $actor = User::factory()->role('39th_ib')->create();
        $municipality = Municipality::query()->create(['name' => 'Stage Two Municipality']);
        $barangay = Barangay::query()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Stage Two Barangay',
        ]);

        return [$actor, $municipality, $barangay];
    }

    private function validAttributes(Municipality $municipality, ?Barangay $barangay): array
    {
        return [
            'first_name' => 'Test First',
            'last_name' => 'Test Last',
            'category' => Ib39FrCategory::MilisyaNgBayan->value,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay?->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => false,
        ];
    }

    private function databaseAttributes(User $actor, Municipality $municipality, Barangay $barangay): array
    {
        return [
            'first_name' => 'Test First',
            'last_name' => 'Test Last',
            'category' => Ib39FrCategory::RegularMember->value,
            'province' => Ib39SurfacedFormerRebel::DEFAULT_PROVINCE,
            'municipality_id' => $municipality->id,
            'barangay_id' => $barangay->id,
            'surfaced_at' => '2026-08-30',
            'possessed_firearms' => 0,
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
