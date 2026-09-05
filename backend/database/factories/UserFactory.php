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
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_PETUGAS,
            'wilayah_scope' => null,
            'avatar_initial' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function petugas(?string $wilayah = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_PETUGAS,
            'wilayah_scope' => $wilayah,
        ]);
    }

    public function pengawas(?string $wilayah = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_PENGAWAS,
            'wilayah_scope' => $wilayah,
        ]);
    }

    public function dinas(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_DINAS,
            'wilayah_scope' => null,
        ]);
    }
}
