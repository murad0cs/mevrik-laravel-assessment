<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240', // Max 10MB
                'mimes:txt,csv,json,jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx'
            ],
            'processing_type' => [
                'required',
                'string',
                'in:text_transform,csv_analyze,image_resize,json_format,metadata'
            ],
            'user_id' => [
                'nullable',
                'integer',
                'min:1'
            ],
            'metadata' => [
                'nullable',
                'array'
            ],
            'metadata.*' => [
                'string',
                'max:1000'
            ]
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a file',
            'file.max' => 'File size must not exceed 10MB',
            'file.mimes' => 'Unsupported file type',
            'processing_type.required' => 'Please specify a processing type',
            'processing_type.in' => 'Invalid processing type. Valid types are: text_transform, csv_analyze, image_resize, json_format, metadata',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'processing_type' => 'processing type',
            'user_id' => 'user ID',
        ];
    }
}