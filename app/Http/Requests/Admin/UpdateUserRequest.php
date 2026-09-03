<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The password rules are spelled out rather than taken from
     * PasswordValidationRules because that trait marks the field required; an
     * administrator editing a name should not be forced to reset credentials.
     * A blank value leaves the existing password in place.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => [...$this->emailRules($this->targetUser()->id), 'lowercase'],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'email_verified' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * The user being edited, resolved from the route binding.
     */
    public function targetUser(): User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : User::query()->findOrFail((int) $user);
    }
}
