<?php

namespace Ahs12\Setanjo\Database\Factories;

use Ahs12\Setanjo\Enums\SettingType;
use Ahs12\Setanjo\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(SettingType::cases());

        return [
            'key' => $this->faker->unique()->word(),
            'value' => $this->generateValueForType($type),
            'description' => $this->faker->optional()->sentence(),
            'type' => $type,
        ];
    }

    /**
     * Generate a value based on the specified type
     */
    private function generateValueForType(SettingType $type): string
    {
        return match ($type) {
            SettingType::BOOLEAN => $this->faker->boolean() ? '1' : '0',
            SettingType::INTEGER => (string) $this->faker->numberBetween(1, 1000),
            SettingType::FLOAT => (string) $this->faker->randomFloat(2, 0, 1000),
            SettingType::ARRAY , SettingType::JSON => json_encode($this->faker->words(3)),
            SettingType::OBJECT => json_encode([
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ]),
            SettingType::STRING => $this->faker->sentence(),
        };
    }

    /**
     * Create a global setting (no tenant)
     */
    public function global(): static
    {
        return $this->state(function (array $attributes) {
            $column = config('setanjo.tenantable_column', 'tenantable');

            return [
                $column.'_type' => null,
                $column.'_id' => null,
            ];
        });
    }

    /**
     * Create a setting for a specific tenant
     */
    public function forTenant($tenant): static
    {
        return $this->state(function (array $attributes) use ($tenant) {
            $column = config('setanjo.tenantable_column', 'tenantable');

            return [
                $column.'_type' => get_class($tenant),
                $column.'_id' => $tenant->getKey(),
            ];
        });
    }

    /**
     * Create a string type setting
     */
    public function string(): static
    {
        return $this->state([
            'type' => SettingType::STRING->value,
            'value' => $this->faker->sentence(),
        ]);
    }

    /**
     * Create a boolean type setting
     */
    public function boolean(): static
    {
        return $this->state([
            'type' => SettingType::BOOLEAN->value,
            'value' => $this->faker->boolean() ? '1' : '0',
        ]);
    }

    /**
     * Create an integer type setting
     */
    public function integer(): static
    {
        return $this->state([
            'type' => SettingType::INTEGER->value,
            'value' => (string) $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Create a float type setting
     */
    public function float(): static
    {
        return $this->state([
            'type' => SettingType::FLOAT->value,
            'value' => (string) $this->faker->randomFloat(2, 0, 1000),
        ]);
    }

    /**
     * Create an array type setting
     */
    public function array(): static
    {
        return $this->state([
            'type' => SettingType::ARRAY->value,
            'value' => json_encode($this->faker->words(3)),
        ]);
    }

    /**
     * Create an object type settings
     */
    public function object(): static
    {
        return $this->state([
            'type' => SettingType::OBJECT->value,
            'value' => json_encode([
                'name' => $this->faker->name(),
                'email' => $this->faker->email(),
            ]),
        ]);
    }
}
