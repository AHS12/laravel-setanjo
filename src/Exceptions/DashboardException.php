<?php

namespace Ahs12\Setanjo\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A dashboard request that cannot be carried out; renders as a JSON error.
 */
class DashboardException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(string $message, protected int $status, protected array $errors = [])
    {
        parent::__construct($message);
    }

    public static function notFound(string $message): self
    {
        return new self($message, Response::HTTP_NOT_FOUND);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function invalid(string $message, array $errors = []): self
    {
        return new self($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'errors' => $this->errors ?: null,
        ]), $this->status);
    }
}
