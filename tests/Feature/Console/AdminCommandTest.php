<?php

use App\Enums\AuditEvent;
use App\Enums\PlatformRole;
use App\Models\AuditRecord;
use App\Models\User;
use Illuminate\Testing\PendingCommand;

/**
 * Run the command through its interactive prompts.
 */
function createAdmin(string $password, ?string $confirmation = null): PendingCommand
{
    return test()->artisan('sso:admin', ['--name' => 'Ada Admin', '--email' => 'ada@example.test'])
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation ?? $password);
}

test('it creates an administrator holding every platform permission', function () {
    createAdmin('correct-horse-battery-staple')->assertSuccessful();

    $administrator = User::where('email', 'ada@example.test')->sole();

    expect($administrator->hasRole(PlatformRole::SuperAdmin->value))->toBeTrue()
        ->and($administrator->can('sso.applications.manage'))->toBeTrue();
});

test('the new administrator can sign in immediately', function () {
    /*
     * Nothing can send them a verification link on a fresh install, so an
     * account made from the console has to be usable without one.
     */
    createAdmin('correct-horse-battery-staple')->assertSuccessful();

    $this->post('/login', [
        'email' => 'ada@example.test',
        'password' => 'correct-horse-battery-staple',
    ]);

    $this->assertAuthenticated();
    expect(User::where('email', 'ada@example.test')->sole()->hasVerifiedEmail())->toBeTrue();
});

test('a mistyped confirmation creates nobody', function () {
    createAdmin('correct-horse-battery-staple', 'correct-horse-battery-stapel')->assertFailed();

    expect(User::where('email', 'ada@example.test')->exists())->toBeFalse();
});

test('a weak password is refused', function () {
    createAdmin('secret')->assertFailed();

    expect(User::where('email', 'ada@example.test')->exists())->toBeFalse();
});

test('an existing user is promoted rather than duplicated', function () {
    $user = User::factory()->create(['email' => 'grace@example.test']);
    $password = $user->password;

    $this->artisan('sso:admin', ['--email' => 'grace@example.test'])
        ->expectsConfirmation('grace@example.test already exists. Make them an administrator?', 'yes')
        ->assertSuccessful();

    expect(User::where('email', 'grace@example.test')->count())->toBe(1)
        ->and($user->refresh()->hasRole(PlatformRole::SuperAdmin->value))->toBeTrue()
        ->and($user->password)->toBe($password);
});

test('declining the promotion changes nothing', function () {
    $user = User::factory()->create(['email' => 'grace@example.test']);

    $this->artisan('sso:admin', ['--email' => 'grace@example.test'])
        ->expectsConfirmation('grace@example.test already exists. Make them an administrator?', 'no')
        ->assertFailed();

    expect($user->refresh()->hasRole(PlatformRole::SuperAdmin->value))->toBeFalse();
});

test('promoting someone who is already an administrator is not an error', function () {
    User::factory()->superAdmin()->create(['email' => 'ada@example.test']);

    $this->artisan('sso:admin', ['--email' => 'ada@example.test'])->assertSuccessful();
});

test('it runs without a terminal, generating a password', function () {
    $this->artisan('sso:admin', [
        '--name' => 'Ada Admin',
        '--email' => 'ada@example.test',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::where('email', 'ada@example.test')->exists())->toBeTrue();
});

test('creating an administrator is recorded in the audit trail', function () {
    $this->artisan('sso:admin', [
        '--name' => 'Ada Admin',
        '--email' => 'ada@example.test',
        '--no-interaction' => true,
    ])->assertSuccessful();

    /*
     * Creating a super administrator is the single most security-relevant
     * action on the server, so the console dispatches the same event the
     * controller does rather than writing silently.
     *
     * What that record does and does not carry is AuditLoggerTest's subject.
     */
    $record = AuditRecord::where('event', AuditEvent::UserCreated->value)->sole();

    expect($record->subject->email)->toBe('ada@example.test');
});

test('a mixed-case address is refused, as it is on every other path', function () {
    /*
     * An address is this server's subject identity, and "unique" is case
     * sensitive on PostgreSQL, so two accounts differing only in case could
     * otherwise each receive tokens as a different person. The console used to
     * accept what the administration form rejected.
     */
    $this->artisan('sso:admin', [
        '--name' => 'Ada Admin',
        '--email' => 'Ada@Example.test',
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('an administrator is never created without their role', function () {
    /*
     * Account, verification and role are one transaction. A user row with no
     * platform role is an administrator who cannot administer, which is the
     * one outcome this command exists to prevent.
     */
    $this->artisan('sso:admin', [
        '--name' => 'Ada Admin',
        '--email' => 'ada@example.test',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::query()->doesntHave('roles')->count())->toBe(0);
});

test('the password is never accepted as an argument', function () {
    /*
     * Arguments are readable in shell history and in the process list, so
     * there is deliberately no option to pass one.
     */
    expect(app('Illuminate\Contracts\Console\Kernel')->all()['sso:admin']->getDefinition()->hasOption('password'))
        ->toBeFalse();
});
