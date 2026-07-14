<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware and policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'settings' => 'nullable|array',
            'settings.creativity_level' => 'sometimes|string|max:50',
            'settings.aspect_ratio' => 'sometimes|string|max:20',
            'is_favorite' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        // Clients must never set privileged pipeline flags.
        if (isset($data['settings']) && is_array($data['settings'])) {
            unset(
                $data['settings']['wizard_type'],
                $data['settings']['skip_credits'],
                $data['settings']['history_ids'],
                $data['settings']['prompt_batch'],
                $data['settings']['cluster_csv_pipeline'],
            );
        }

        return $data;
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Project title is required.',
            'title.max' => 'Project title cannot exceed 255 characters.',
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 2000 characters.',
        ];
    }
}
