<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ValidationException extends Exception
{
    protected array $errors;

    /**
     * Create a new validation exception
     */
    public function __construct(
        array $errors,
        string $message = 'The given data was invalid.',
        int $code = 422,
        ?Exception $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Render the exception into an HTTP response
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => $this->errors,
            ], 422);
        }

        return redirect()
            ->back()
            ->withInput()
            ->withErrors($this->errors);
    }

    /**
     * Create exception for missing required field
     */
    public static function missingField(string $field): self
    {
        return new self(
            [$field => ["The {$field} field is required."]],
            "Missing required field: {$field}"
        );
    }

    /**
     * Create exception for invalid field value
     */
    public static function invalidValue(string $field, string $reason): self
    {
        return new self(
            [$field => [$reason]],
            "Invalid value for field: {$field}"
        );
    }

    /**
     * Create exception for multiple validation errors
     */
    public static function multiple(array $errors): self
    {
        return new self($errors);
    }
}