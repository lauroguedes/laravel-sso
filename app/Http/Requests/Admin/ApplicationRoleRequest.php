<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ResolvesApplicationFromRoute;
use App\Models\ApplicationRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationRoleRequest extends FormRequest
{
    use ResolvesApplicationFromRoute;

    /**
     * Get the validation rules that apply to the request.
     *
     * Serves both creating and editing, since the only difference is which
     * record the uniqueness check ignores.
     *
     * Role names are unique within their application only, so the same name
     * may exist in several applications without conflict.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $application = $this->application();

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('application_roles', 'name')
                    ->where('application_id', $application->id)
                    ->ignore($this->role()?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [
                'integer',
                Rule::exists('application_permissions', 'id')
                    ->where('application_id', $application->id),
            ],
        ];
    }

    /**
     * The role being edited, when this is an update rather than a creation.
     */
    private function role(): ?ApplicationRole
    {
        $role = $this->route('role');

        return $role instanceof ApplicationRole ? $role : null;
    }
}
