<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FormerRebel;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MblrcFormerRebelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Municipality $municipality;

    private Barangay $barangay;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-25 12:00:00');
        $this->user = User::factory()->role('mblrc')->create();
        $this->municipality = Municipality::query()->create(['name' => 'Sulop']);
        $this->barangay = Barangay::query()->create([
            'municipality_id' => $this->municipality->id,
            'name' => 'Poblacion',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_age_is_derived_when_the_birthday_has_occurred_this_year_and_cannot_be_forged(): void
    {
        $this->actingAs($this->user)
            ->post(route('mblrc.fr.store'), $this->validPayload([
                'birthdate' => '2000-09-25',
                'age' => 'not-the-real-age',
            ]))
            ->assertRedirect();

        $this->assertSame(26, FormerRebel::query()->sole()->age);
    }

    public function test_age_is_derived_when_the_birthday_has_not_yet_occurred_this_year(): void
    {
        $this->actingAs($this->user)
            ->post(route('mblrc.fr.store'), $this->validPayload([
                'birthdate' => '2000-09-26',
                'age' => 99,
            ]))
            ->assertRedirect();

        $this->assertSame(25, FormerRebel::query()->sole()->age);
    }

    public function test_future_birthdate_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->post(route('mblrc.fr.store'), $this->validPayload([
                'birthdate' => '2026-09-26',
            ]))
            ->assertSessionHasErrors('birthdate');

        $this->assertDatabaseCount('former_rebels', 0);
    }

    public function test_valid_canonical_phone_number_is_accepted(): void
    {
        $this->actingAs($this->user)
            ->post(route('mblrc.fr.store'), $this->validPayload([
                'contact_num' => '09171234567',
            ]))
            ->assertRedirect();

        $this->assertSame('09171234567', FormerRebel::query()->sole()->contact_num);
    }

    #[DataProvider('invalidPhoneNumbers')]
    public function test_invalid_phone_numbers_are_rejected(string $phone): void
    {
        $this->actingAs($this->user)
            ->post(route('mblrc.fr.store'), $this->validPayload([
                'contact_num' => $phone,
            ]))
            ->assertSessionHasErrors('contact_num');

        $this->assertDatabaseCount('former_rebels', 0);
    }

    public static function invalidPhoneNumbers(): array
    {
        return [
            'too short' => ['0917123456'],
            'too long' => ['091712345678'],
            'wrong prefix' => ['08171234567'],
            'missing leading zero' => ['91712345678'],
            'letters' => ['0917ABC4567'],
            'hyphens' => ['0917-123-4567'],
        ];
    }

    public function test_existing_create_and_edit_forms_share_read_only_age_and_restricted_phone_fields(): void
    {
        $formerRebel = FormerRebel::query()->create(array_merge($this->validPayload(), [
            'classified_id' => 'FR-#0001',
            'age' => 26,
        ]));

        foreach ([route('mblrc.fr.create'), route('mblrc.fr.edit', $formerRebel)] as $url) {
            $html = $this->actingAs($this->user)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('type="number" name="age"', $html);
            $this->assertStringContainsString('readonly aria-readonly="true" data-age-output', $html);
            $this->assertStringContainsString('max="2026-09-25" data-birthdate-input', $html);
            $this->assertStringContainsString('type="tel" name="contact_num"', $html);
            $this->assertStringContainsString('inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX"', $html);
            $this->assertStringContainsString("replace(/\\D/g, '').slice(0, 11)", $html);
            $this->assertStringContainsString("birthdate?.addEventListener('change', calculateAge)", $html);
        }
    }

    public function test_edit_recalculates_age_from_birthdate_instead_of_trusting_submitted_age(): void
    {
        $formerRebel = FormerRebel::query()->create(array_merge($this->validPayload(), [
            'classified_id' => 'FR-#0001',
            'birthdate' => '1990-01-01',
            'age' => 36,
        ]));

        $this->actingAs($this->user)
            ->put(route('mblrc.fr.update', $formerRebel), $this->validPayload([
                'birthdate' => '2000-09-26',
                'age' => 99,
            ]))
            ->assertRedirect(route('mblrc.fr.show', $formerRebel));

        $this->assertSame(25, $formerRebel->fresh()->age);
    }

    public function test_edit_rejects_a_future_birthdate_and_invalid_phone_number(): void
    {
        $formerRebel = FormerRebel::query()->create(array_merge($this->validPayload(), [
            'classified_id' => 'FR-#0001',
            'age' => 26,
        ]));

        $this->actingAs($this->user)
            ->put(route('mblrc.fr.update', $formerRebel), $this->validPayload([
                'birthdate' => '2026-09-26',
                'contact_num' => '0917-123-4567',
            ]))
            ->assertSessionHasErrors(['birthdate', 'contact_num']);

        $formerRebel->refresh();
        $this->assertSame('2000-09-25', $formerRebel->birthdate->toDateString());
        $this->assertSame('09171234567', $formerRebel->contact_num);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Juan',
            'lastname' => 'Dela Cruz',
            'birthdate' => '2000-09-25',
            'age' => 26,
            'contact_num' => '09171234567',
            'municipality_id' => $this->municipality->id,
            'barangay_id' => $this->barangay->id,
        ], $overrides);
    }
}
