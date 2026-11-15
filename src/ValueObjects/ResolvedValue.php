<?php

namespace Ahs12\Setanjo\ValueObjects;

use Ahs12\Setanjo\Enums\ValueSource;
use Illuminate\Database\Eloquent\Model;

/**
 * The effective value of a setting and where it came from.
 */
final readonly class ResolvedValue
{
    public function __construct(
        public string $key,
        public mixed $value,
        public ValueSource $source,
        public ?Model $scope = null,
    ) {}

    public function fromScope(): bool
    {
        return $this->source === ValueSource::SCOPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'source' => $this->source->value,
            'scope_type' => $this->scope ? $this->scope::class : null,
            'scope_id' => $this->scope?->getKey(),
        ];
    }
}
