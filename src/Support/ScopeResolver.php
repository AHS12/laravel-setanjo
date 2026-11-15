<?php

namespace Ahs12\Setanjo\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Builds the scope chain used to resolve effective values.
 *
 * The chain lists the scopes to check, most specific first, e.g. [$user, $branch, $tenant].
 * Global and the definition default are always checked after the chain.
 */
class ScopeResolver
{
    protected ?Closure $chainResolver = null;

    protected ?Closure $subjectResolver = null;

    /**
     * @param  Closure(Model|null): iterable<Model|null>|null  $resolver
     */
    public function resolveScopesUsing(?Closure $resolver): void
    {
        $this->chainResolver = $resolver;
    }

    /**
     * @param  Closure(): (Model|null)|null  $resolver
     */
    public function resolveDefaultSubjectUsing(?Closure $resolver): void
    {
        $this->subjectResolver = $resolver;
    }

    /**
     * The subject used when no scope was given explicitly (default: the authenticated user).
     */
    public function defaultSubject(): ?Model
    {
        $subject = $this->subjectResolver
            ? ($this->subjectResolver)()
            : (function_exists('auth') ? auth()->user() : null);

        return $subject instanceof Model ? $subject : null;
    }

    /**
     * @param  array<int, class-string>  $allowed  scope classes allowed to override (empty = any)
     * @return array<int, Model>
     */
    public function chain(?Model $subject, array $allowed = []): array
    {
        if ($subject === null) {
            return [];
        }

        $scopes = $this->chainResolver ? ($this->chainResolver)($subject) : [$subject];

        $chain = [];
        foreach ($scopes as $scope) {
            if (! $scope instanceof Model || $scope->getKey() === null) {
                continue;
            }

            if ($allowed !== [] && ! $this->isAllowed($scope, $allowed)) {
                continue;
            }

            $chain[$scope::class.':'.$scope->getKey()] = $scope;
        }

        return array_values($chain);
    }

    /**
     * @param  array<int, class-string>  $allowed
     */
    public function isAllowed(Model $scope, array $allowed): bool
    {
        foreach ($allowed as $class) {
            if ($scope instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
