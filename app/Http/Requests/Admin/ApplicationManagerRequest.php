<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Concerns\ResolvesApplicationFromRoute;
use App\Enums\PlatformPermission;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationManagerRequest extends FormRequest
{
    use ResolvesApplicationFromRoute;

    /**
     * Only an administrator of this application.
     *
     * Asked here as well as in the controller because a form request
     * validates first: without it, somebody who may not assign anybody could
     * tell from the answer whether a named user holds the Developer role.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageStewards', $this->application()) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('disabled_at'),
                $this->holdsTheDeveloperPermission(...),
            ],
        ];
    }

    /**
     * Refuse an assignment that would change nothing.
     *
     * Checked here and not left to the picker: the list of candidates is a
     * convenience, and a stored row that grants nothing reads as a fault
     * rather than as the missing role it is.
     *
     * Asked of the user rather than of a table, because the permission
     * usually arrives through the Developer role rather than being attached
     * to the person directly.
     */
    private function holdsTheDeveloperPermission(string $attribute, mixed $value, Closure $fail): void
    {
        $candidate = User::query()->whereKey($value)->first();

        if ($candidate === null || ! $candidate->can(PlatformPermission::ApplicationsDevelop->value)) {
            $fail('This person does not hold the Developer role, so the assignment would grant them nothing.');

            return;
        }

        if ($candidate->can(PlatformPermission::ApplicationsManage->value)) {
            $fail('This person already administers every application, so there is nothing to assign.');
        }
    }
}
