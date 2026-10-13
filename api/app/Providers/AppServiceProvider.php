<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User\Ability;
use App\Support\Mixins\RequestMixin;
use App\Support\Mixins\UrlMixin;
use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\BouncerFacade as Bouncer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->bind(Hashids::class, fn () => new Hashids(config('app.key'), 10));
    }

    public function boot(): void
    {
        Request::mixin(new RequestMixin());
        URL::mixin(new UrlMixin());

        // Use our own role/ability models so custom columns (title, group, excludes) are respected.
        Bouncer::useRoleModel(Role::class);
        Bouncer::useAbilityModel(Ability::class);
    }
}
