<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchNotificationRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['email', 'sms', 'push', 'alert', 'webhook']),
            ],
            'message' => [
                'required',
                'string',
                'min:1',
                'max:5000',
            ],
            'metadata' => [
                'nullable',
                'array',
                'max:50',
            ],
            'metadata.*' => [
                'nullable',
                'string',
                'max:500',
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'schedule_at' => [
                'nullable',
                'date',
                'after:now',
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
            'user_id.required' => 'User ID is required.',
            'user_id.integer' => 'User ID must be a valid number.',
            'type.required' => 'Notification type is required.',
            'type.in' => 'Invalid notification type. Must be: email, sms, push, alert, or webhook.',
            'message.required' => 'Notification message is required.',
            'message.max' => 'Message cannot exceed 5000 characters.',
            'metadata.array' => 'Metadata must be an array.',
            'metadata.max' => 'Metadata cannot have more than 50 items.',
            'priority.in' => 'Priority must be: low, normal, high, or urgent.',
            'schedule_at.date' => 'Schedule time must be a valid date.',
            'schedule_at.after' => 'Schedule time must be in the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'normal']);
        }

        // Ensure user_id is integer
        if ($this->has('user_id')) {
            $this->merge(['user_id' => (int) $this->user_id]);
        }
    }
}