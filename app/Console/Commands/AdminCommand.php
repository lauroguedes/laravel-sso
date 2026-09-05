<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\UserManager;
use Database\Seeders\PlatformPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Creates a platform administrator, or promotes an existing user to one.
 *
 * This is the supported way to obtain the first account that can reach the
 * administration interface, since a freshly installed server has no way to
 * grant anyone anything.
 *
 * The password is never accepted as a command line argument: arguments end up
 * in shell history and in the process list, where any other user of the
 * machine can read them. It is prompted for, or generated and shown once.
 */
class AdminCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /** @var string */
    protected $signature = 'sso:admin
        {--name= : The administrator\'s name}
        {--email= : The administrator\'s email address}';

    /** @var string */
    protected $description = 'Create a platform administrator, or promote an existing user to one';

    /**
     * Execute the console command.
     */
    public function handle(UserManager $users): int
    {
        $this->ensureTheRoleExists();

        $email = $this->optionOrAsk('email', 'Email address');

        if ($email === '') {
            $this->components->error('An email address is required.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        return $existing instanceof User
            ? $this->promote($existing)
            : $this->create($email, $users);
    }

    /**
     * Make sure the Super Admin role is there to be granted.
     *
     * The command never depends on "sso:install" having been run first, but it
     * does not re-run the seeder when the role is already there: an
     * interactive install calls this command straight after seeding, and
     * running it twice rebuilds and flushes the permission cache for nothing.
     */
    private function ensureTheRoleExists(): void
    {
        if (! Role::query()->where('name', PlatformRole::SuperAdmin->value)->exists()) {
            app(PlatformPermissionsSeeder::class)->run();
        }
    }

    /**
     * Read an option, falling back to asking when there is a terminal.
     */
    private function optionOrAsk(string $option, string $question): string
    {
        $given = $this->option($option);

        $value = is_string($given) ? trim($given) : '';

        if ($value === '' && $this->input->isInteractive()) {
            $answer = $this->ask($question);

            $value = is_string($answer) ? trim($answer) : '';
        }

        return $value;
    }

    /**
     * Give an existing user every platform permission.
     *
     * Their password is deliberately left alone: this command grants
     * privileges, and quietly resetting someone's credentials while doing so
     * would be a surprising way to lose access to an account.
     */
    private function promote(User $user): int
    {
        if ($user->hasRole(PlatformRole::SuperAdmin->value)) {
            $this->components->info("{$user->email} is already an administrator.");

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm("{$user->email} already exists. Make them an administrator?", true)) {
            return self::FAILURE;
        }

        $user->assignRole(PlatformRole::SuperAdmin->value);

        $this->components->info("{$user->email} is now an administrator.");

        return self::SUCCESS;
    }

    /**
     * Create a new administrator.
     */
    private function create(string $email, UserManager $users): int
    {
        $generated = ! $this->input->isInteractive();

        $attributes = [
            'name' => $this->optionOrAsk('name', 'Name'),
            'email' => $email,
            ...$this->resolvePassword(),
        ];

        try {
            /*
             * The same rules the registration form and the administration
             * form apply, from the concerns that define them once.
             */
            Validator::make($attributes, [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
            ])->validate();
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        /*
         * The address is marked verified because whoever ran this command
         * already has shell access to the server, which is a stronger claim on
         * the account than receiving a message at the address, and a
         * self-hosted install often has no mail transport on day one.
         *
         * Role, verification and account are one call so that they are also
         * one transaction: a half-created administrator is the state this
         * command exists to avoid.
         */
        $user = $users->create([
            ...$attributes,
            'email_verified' => true,
            'roles' => [PlatformRole::SuperAdmin->value],
        ]);

        $this->components->info("Administrator {$user->email} created.");

        if ($generated) {
            $this->components->twoColumnDetail('Generated password', $attributes['password']);
            $this->components->warn('This password is shown once. Store it now, and change it after signing in.');
        }

        return self::SUCCESS;
    }

    /**
     * Ask for a password, or generate one when nobody can be asked.
     *
     * The confirmation is carried alongside rather than assumed, so that the
     * shared "confirmed" rule still catches a mistyped password here exactly
     * as it does on the registration form.
     *
     * @return array{password: string, password_confirmation: string}
     */
    private function resolvePassword(): array
    {
        if (! $this->input->isInteractive()) {
            $password = Str::password(24);

            return ['password' => $password, 'password_confirmation' => $password];
        }

        return [
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Confirm password'),
        ];
    }
}
