<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ResolvesApplicationFromRoute;
use App\Models\ApplicationRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationGrantRequest extends FormRequest
{
    use ResolvesApplicationFromRoute;

    /**
     * Get the validation rules that apply to the request.
     *
     * The role is always validated against this application's own roles, so a
     * role belonging to another application cannot be assigned by guessing its
     * id. The user is only named when access is first granted; afterwards only
     * the role changes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $application = $this->application();

        $rules = [
            'application_role_id' => [
                'nullable',
                Rule::exists('application_roles', 'id')
                    ->where('application_id', $application->id),
            ],
        ];

        if ($this->route('grant') === null) {
            $rules['user_id'] = [
                'required',
                Rule::exists(User::class, 'id'),
                Rule::unique('application_user', 'user_id')
                    ->where('application_id', $application->id),
            ];
        }

        return $rules;
    }

    /**
     * The role to assign, once validation has confirmed it belongs here.
     */
    public function applicationRole(): ?ApplicationRole
    {
        $roleId = $this->validated('application_role_id');

        return $roleId === null
            ? null
            : ApplicationRole::query()->findOrFail((int) $roleId);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.unique' => 'That user already has access to this application.',
        ];
    }
}
