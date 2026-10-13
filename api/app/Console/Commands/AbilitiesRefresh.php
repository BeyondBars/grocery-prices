<?php

namespace App\Console\Commands;

use Bouncer;
use Illuminate\Support\Str;
use App\Models\User\Ability;
use App\Support\Enums\UserAbility;
use App\Support\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Silber\Bouncer\Database\Role as BouncerRole;

class AbilitiesRefresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'abilities:refresh
                            {--C|clean : Cleans dangling abilities that have been removed from the system}
                            {--no-confirmation : Do not require confirmation when cleaning dangling abilities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Non-destructive refresh of the systems roles/abilities';

    /**
     * Collection of defined abilities that are created/found.
     *
     * @var \Illuminate\Support\Collection[\Silber\Bouncer\Database\Ability]
     */
    protected $abilities;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->abilities = new Collection;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->createAndUpdateAbilities();

        // Create every role defined in the UserRole enum and sync its default abilities.
        foreach (UserRole::cases() as $role) {
            $this->assignAbilitiesToRole($this->createRole($role), $role->defaultPermissions());
        }

        $this->info('Roles created and abilities assigned.');

        if ($this->option('clean')) {
            $this->cleanAbilities();
        }
    }

    /**
     * Assign abilities to a Bouncer role.
     *
     * @param \Silber\Bouncer\Database\Role $role
     * @param array $abilities
     * @return void
     */
    protected function assignAbilitiesToRole(BouncerRole $role, array $abilities): void
    {
        $abilities = Bouncer::ability()->whereIn('name', $abilities)->get();

        // Remove abilities existing on the role but not in the assigning abilities array.
        $role->disallow($role->abilities->diff($abilities));

        $role->allow($abilities);
    }

    /**
     * Create a Bouncer role in the database or return an existing role.
     *
     * @param \App\Support\Enums\UserRole $role
     * @return \Silber\Bouncer\Database\Role
     */
    protected function createRole(UserRole $role): BouncerRole
    {
        return tap(
            Bouncer::role()->firstOrNew(['name' => $role->value]),
            fn (BouncerRole $model) => $model->forceFill([
                'title' => $role->title()
            ])->save()
        );
    }

    /**
     * Create the abilities.
     *
     * @return void
     */
    protected function createAndUpdateAbilities(): void
    {
        collect(UserAbility::grouped())->each(function ($abilities, $group) {
            collect($abilities)->each(function ($data, $title) use ($group) {
                $this->abilities[] = Ability::updateOrCreate([
                    'name' => Str::lower($data['name']->value),
                ], [
                    'title' => Str::title($title),
                    'group' => $group,
                    'excludes' => $data['excludes']
                ]);
            });
        });

        $this->info('Abilities created.');
    }

    /**
     * Clean dangling abilities that are no longer being used by any user/role.
     *
     * Only applies to abilities that have no entity.
     *
     * @return void
     */
    protected function cleanAbilities(): void
    {
        $this->line('');
        $this->warn('! Cleaning dangling simple abilities that are no longer defined.');

        if ($this->option('no-confirmation') === false) {
            $this->warn('! You will be prompted for each ability for confirmation.');
        }

        $this->line('');

        Bouncer::ability()
            ->simpleAbility()
            ->get()

            // Doing a diff against the abilities we found/created will return us the ones that are no
            // longer being defined and have therefore been removed.
            ->diff($this->abilities)
            ->each(function ($ability) {
                $this->line(sprintf('<fg=yellow>[%s]:</> %s users and %s roles associated with ability.', $ability->name, $ability->users()->count(), $ability->roles()->count()));

                if ($this->option('no-confirmation') || $this->confirm('Would you like to detach users/roles and delete this ability?')) {
                    $ability->users()->detach();
                    $ability->roles()->detach();
                    $ability->delete();
                }
            });

        $this->info('Abilities cleaned!');
    }
}
