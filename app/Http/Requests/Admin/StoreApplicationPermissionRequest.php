<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ResolvesApplicationFromRoute;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationPermissionRequest extends FormRequest
{
    use ResolvesApplicationFromRoute;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $application = $this->application();

        return [
            'name' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+([._:-][a-z0-9]+)*$/',
                Rule::unique('application_permissions', 'name')
                    ->where('application_id', $application->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Use lowercase words separated by dots, such as "reports.view".',
        ];
    }
}
