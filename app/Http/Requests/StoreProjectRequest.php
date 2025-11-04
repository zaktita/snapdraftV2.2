<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'settings' => 'nullable|array',
            
            // For wizard-based creation
            'brand_references' => 'nullable|array|max:10',
            'brand_references.*' => 'image|mimes:jpeg,jpg,png,webp|max:10240', // 10MB max
            
            // For CSV wizard
            'csv_file' => 'nullable|file|mimes:csv,txt|max:5120', // 5MB max
            'csv_data' => 'nullable|array',
            
            // For text wizard
            'prompt' => 'nullable|string|max:5000',
            'format' => 'nullable|string|in:square,portrait,landscape',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'brand_references.max' => 'You can upload a maximum of 10 brand reference images.',
            'brand_references.*.image' => 'All brand references must be valid images.',
            'brand_references.*.mimes' => 'Brand references must be in JPG, PNG, or WebP format.',
            'brand_references.*.max' => 'Each brand reference image must not exceed 10MB.',
            'csv_file.mimes' => 'CSV file must be in CSV or TXT format.',
            'csv_file.max' => 'CSV file must not exceed 5MB.',
            'format.in' => 'Format must be square, portrait, or landscape.',
        ];
    }
}
