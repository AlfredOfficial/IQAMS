<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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

    public function create($attributes = [], ?Model $parent = null)
    {
        $legacyAttributes = is_array($attributes) ? $attributes : [];
        $result = parent::create($attributes, $parent);
        $models = $result instanceof User ? [$result] : $result;

        foreach ($models as $user) {
            $state = [];

            foreach (['must_change_password', 'password_changed_at'] as $field) {
                if (array_key_exists($field, $legacyAttributes)) {
                    $state[$field] = $legacyAttributes[$field];
                }
            }

            if (! empty($legacyAttributes['role_id']) && ($role = Role::find($legacyAttributes['role_id']))) {
                $user->syncRoles([$role]);
                $state['role_id'] = $role->id;
            }

            if ($state !== []) {
                $user->forceFill($state)->saveQuietly();
            }
        }

        return $result;
    }

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
            'status' => 'active',
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
        ];
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
