<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => [
                'required',
                'string',
                'min:1',
                'max:10000',
            ],
            'level' => [
                'required',
                'string',
                Rule::in(['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']),
            ],
            'context' => [
                'nullable',
                'array',
                'max:100',
            ],
            'context.*' => [
                'nullable',
            ],
            'source' => [
                'nullable',
                'string',
                'max:100',
            ],
            'user_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'request_id' => [
                'nullable',
                'string',
                'max:100',
            ],
            'tags' => [
                'nullable',
                'array',
                'max:10',
            ],
            'tags.*' => [
                'string',
                'max:50',
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'Log message is required.',
            'message.max' => 'Log message cannot exceed 10000 characters.',
            'level.required' => 'Log level is required.',
            'level.in' => 'Invalid log level. Must be one of: debug, info, notice, warning, error, critical, alert, emergency.',
            'context.array' => 'Context must be an array.',
            'context.max' => 'Context cannot have more than 100 items.',
            'source.max' => 'Source cannot exceed 100 characters.',
            'tags.array' => 'Tags must be an array.',
            'tags.max' => 'Cannot have more than 10 tags.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default level if not provided
        if (!$this->has('level')) {
            $this->merge(['level' => 'info']);
        }

        // Set request ID if not provided
        if (!$this->has('request_id')) {
            $this->merge(['request_id' => uniqid('req_', true)]);
        }

        // Ensure source is set
        if (!$this->has('source')) {
            $this->merge(['source' => 'api']);
        }
    }
}