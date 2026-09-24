<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'lgu',
            'remember_token' => Str::random(10),
        ];
    }

    /** Set the user's role. */
    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    /** LGU user scoped to a municipality. */
    public function lgu(?int $municipalityId = null): static
    {
        return $this->state(fn () => ['role' => 'lgu', 'municipality_id' => $municipalityId]);
    }

    /** Government-agency user scoped to an agency. */
    public function govAgency(?int $agencyId = null): static
    {
        return $this->state(fn () => ['role' => 'gov_agency', 'gov_agency_id' => $agencyId]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
