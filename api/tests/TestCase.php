<?php

namespace Tests;

use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\InteractsWithTime;
use Silber\Bouncer\BouncerFacade;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithTime;

    protected function setUp(): void
    {
        $this->setUpParallel();

        parent::setUp();

        $this->withoutMiddleware([ThrottleRequests::class]);

        // Makes requests "stateful" for Sanctum (as if they came from the SPA).
        $this->withHeaders(['referer' => config('app.url')]);

        // Two factor is treated as verified unless the test class opts out.
        if (!method_exists($this, 'shouldTwoFactorBeVerified') || $this->shouldTwoFactorBeVerified()) {
            $this->withTwoFactorVerified();
        }

        BouncerFacade::dontCache();
        BouncerFacade::scope()->to(null);

        if (in_array(RefreshDatabase::class, class_uses_recursive($this))) {
            Artisan::call('abilities:refresh');
        }
    }

    public function createStandardUser(array $attributes = []): User
    {
        return User::factory()->standardUser()->create($attributes);
    }

    public function createUserWithRole(UserRole $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    public function createAdminUser(array $attributes = []): User
    {
        return User::factory()->superAdminUser()->enabled()->create($attributes);
    }

    public function beAdmin(array $attributes = []): self
    {
        return $this->be($this->createAdminUser($attributes));
    }

    public function beUser(array $attributes = []): self
    {
        return $this->be($this->createStandardUser($attributes));
    }

    public function beUserAbleTo(?string $ability = null): self
    {
        $user = $this->createStandardUser();

        if ($ability) {
            $user->allow($ability);
        }

        return $this->be($user);
    }

    protected function withTwoFactorVerified(): void
    {
        $this->withSession(['two_factor_verified' => true]);
    }

    protected function setUpParallel(): void
    {
        if (($parallel = getenv('TEST_TOKEN')) !== false) {
            putenv("DB_TESTING_DATABASE=testing_{$parallel}");
        }
    }

    protected function fakeSignature(array $signatureParameters, $expires = null): string
    {
        URL::setKeyResolver(fn () => 'key');

        if ($expires) {
            $signatureParameters = $signatureParameters + ['expires' => $this->availableAt($expires)];
        }

        ksort($signatureParameters);

        return hash_hmac('sha256', implode('.', $signatureParameters), 'key');
    }
}
