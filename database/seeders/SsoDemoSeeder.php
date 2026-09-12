<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationType;
use App\Models\Application;
use App\Models\ApplicationPermission;
use App\Models\ApplicationRole;
use App\Models\User;
use App\Services\ApplicationManager;
use App\Services\DemoMode;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fills a development install with something to look at.
 *
 * Creates an administrator, a developer, a few ordinary users, two
 * applications of different kinds, and the roles and permissions that show how
 * per-application authorization works.
 *
 * Every account here uses a password somebody can look up, so the seeder
 * refuses to run in production rather than trusting whoever typed the command.
 */
class SsoDemoSeeder extends Seeder
{
    /**
     * The password shared by every demo account.
     *
     * The administrator is the exception on a public demonstration, where it
     * gets a fresh one on every reset. See DemoMode.
     */
    public const PASSWORD = 'secret';

    /**
     * The domain every demo address and URL is built from.
     *
     * ".test" is reserved for exactly this by RFC 6761, so nothing here can
     * collide with a real host or accidentally reach one. Change this one
     * constant to move the whole demo onto a domain you control.
     */
    public const DOMAIN = 'user.test';

    /**
     * The command running this seeder, when one is.
     *
     * The parent holds the same thing, but declares it as always present; it
     * is in fact unset whenever a seeder is run outside a console command, as
     * the tests do. Keeping a nullable copy lets the summary be printed
     * without pretending there is always somewhere to print it.
     */
    private ?Command $console = null;

    /**
     * Remember the command, so the summary can be shown to whoever ran it.
     *
     * @return $this
     */
    public function setCommand(Command $command)
    {
        $this->console = $command;

        return parent::setCommand($command);
    }

    public function __construct(private readonly DemoMode $demo) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'SsoDemoSeeder creates accounts with a known password and will not run in production.'
            );
        }

        /*
         * Called rather than callOnce(): callOnce() remembers for the life of
         * the process, which outlives a database that is rolled back between
         * tests. The permissions seeder is idempotent, so calling it plainly
         * costs nothing and is always correct.
         */
        $this->call(PlatformPermissionsSeeder::class);

        /*
         * A rotating password on a public demonstration, so that whatever the
         * last visitor wrote down stops working at the next reset. Everywhere
         * else the shared constant, because a local checkout is more useful
         * with credentials somebody can remember.
         */
        $administratorPassword = $this->demo->rotate(self::email('admin')) ?? self::PASSWORD;

        DB::transaction(function () use ($administratorPassword): void {
            $applications = app(ApplicationManager::class);

            [$reporting, $roles] = $this->reporting($applications);
            $this->singlePageApplication($applications);

            /*
             * The developer looks after one application and not the other, so
             * the demo shows the scoping rather than just the role.
             */
            $reporting->managers()->attach($this->developer());

            /*
             * Only the reporting application restricts access, so the demo
             * shows both halves of the model: an application anyone signed in
             * may reach, and one an administrator admits people to by name.
             *
             * The disabled user is left without a grant, so that the demo also
             * shows an account that exists and reaches nothing.
             */
            $reporting->grantAccessTo($this->administrator($administratorPassword), $roles['Analyst']);

            foreach ($this->users()->whereNull('disabled_at') as $user) {
                $reporting->grantAccessTo($user, $roles['Viewer']);
            }
        });

        $this->console?->newLine();
        $this->console?->info('Demo data created.');
        $this->console?->line('Administrator: '.self::email('admin').'  '.$administratorPassword);
        $this->console?->line('Developer:     '.self::email('dev').' (looks after Reporting)');
        $this->console?->line('Every other demo account uses the password "'.self::PASSWORD.'".');
    }

    /**
     * An address at the demo domain.
     */
    private static function email(string $mailbox): string
    {
        return $mailbox.'@'.self::DOMAIN;
    }

    /**
     * A URL on a subdomain of the demo domain.
     */
    private static function url(string $subdomain, string $path): string
    {
        return 'https://'.$subdomain.'.'.self::DOMAIN.$path;
    }

    /**
     * The demo administrator.
     */
    private function administrator(string $password): User
    {
        return User::factory()->superAdmin()->create([
            'name' => 'Ada Admin',
            'email' => self::email('admin'),
            'password' => $password,
        ]);
    }

    /**
     * The demo developer.
     *
     * Holds the Developer role and is assigned one application by the caller.
     * The role alone reaches nothing, which is the point of it — the demo
     * would not show that if the assignment came with the role.
     */
    private function developer(): User
    {
        return User::factory()->developer()->create([
            'name' => 'Dana Dev',
            'email' => self::email('dev'),
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * Three ordinary users, one of them disabled.
     *
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        return collect([
            ['name' => 'Grace Hopper', 'email' => self::email('grace')],
            ['name' => 'Alan Turing', 'email' => self::email('alan')],
        ])->map(fn (array $attributes): User => User::factory()->create([
            ...$attributes,
            'password' => self::PASSWORD,
        ]))->push(
            User::factory()->disabled()->create([
                'name' => 'Former Employee',
                'email' => self::email('former'),
                'password' => self::PASSWORD,
            ])
        );
    }

    /**
     * A confidential web application with roles, permissions and a guest list.
     *
     * Returns the roles alongside it so the caller can hand them out without
     * reading back rows it has just written.
     *
     * @return array{0: Application, 1: array<string, ApplicationRole>}
     */
    private function reporting(ApplicationManager $applications): array
    {
        $reporting = $applications->create([
            'name' => 'Reporting',
            'description' => 'A server-side web application that reads report data.',
            'type' => ApplicationType::Confidential,
            'redirect_uris' => [self::url('reporting', '/auth/callback')],
            'post_logout_redirect_uris' => [self::url('reporting', '/signed-out')],
            'scopes' => ['openid', 'profile', 'email', 'roles'],
        ]);

        $reporting->forceFill(['restricts_access' => true])->save();

        $permissions = collect(['reports.view', 'reports.export', 'reports.manage'])
            ->mapWithKeys(fn (string $name): array => [
                $name => $reporting->permissions()->create(['name' => $name]),
            ]);

        return [$reporting, [
            'Viewer' => $this->role($reporting, 'Viewer', $permissions->only('reports.view')),
            'Analyst' => $this->role($reporting, 'Analyst', $permissions->only(['reports.view', 'reports.export'])),
            'Owner' => $this->role($reporting, 'Owner', $permissions),
        ]];
    }

    /**
     * A public single page application, open to anyone who can sign in.
     */
    private function singlePageApplication(ApplicationManager $applications): void
    {
        $applications->create([
            'name' => 'Team Dashboard',
            'description' => 'A browser application that signs in with PKCE and holds no secret.',
            'type' => ApplicationType::Public,
            'redirect_uris' => [self::url('dashboard', '/callback'), 'http://localhost:5173/callback'],
            'scopes' => ['openid', 'profile', 'email'],
        ]);
    }

    /**
     * Define one role and attach its permissions.
     *
     * attach() rather than sync(): the role was created a line earlier, so
     * there is nothing for sync() to reconcile against.
     *
     * @param  Collection<string, ApplicationPermission>  $permissions
     */
    private function role(Application $application, string $name, Collection $permissions): ApplicationRole
    {
        /** @var ApplicationRole $role */
        $role = $application->roles()->create(['name' => $name]);

        $role->permissions()->attach($permissions->pluck('id')->all());

        return $role;
    }
}
