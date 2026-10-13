<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\TwoFactorVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldTwoFactorBeVerified(): bool
    {
        return false;
    }

    protected function userWithTwoFactor(string $method = 'email'): User
    {
        return User::factory()->superAdminUser()->withTwoFactor($method)->create([
            'email' => 'admin@example.com',
            'password' => 'Secret*12345',
        ]);
    }

    public function testLoginSendsACodeWhenTwoFactorIsEnabled()
    {
        Notification::fake();
        $user = $this->userWithTwoFactor();

        $this->postJson('auth', ['email' => 'admin@example.com', 'password' => 'Secret*12345'])
            ->assertSuccessful()
            ->assertJsonPath('data.two_factor_verified', false);

        Notification::assertSentTo($user, TwoFactorVerificationNotification::class);
        $this->assertDatabaseCount('user_verification_codes', 1);
    }

    public function testRoutesAreBlockedUntilTheCodeIsVerified()
    {
        Notification::fake();
        $this->userWithTwoFactor();

        $this->postJson('auth', ['email' => 'admin@example.com', 'password' => 'Secret*12345'])->assertSuccessful();

        // Password alone isn't enough.
        $this->getJson('users')->assertForbidden()->assertJsonPath('code', 'two_factor_required');
        $this->putJson('two-factor', ['two_factor_required' => false])->assertForbidden();

        // But the user endpoint (to drive the frontend) is available.
        $this->getJson('user')->assertSuccessful();
    }

    public function testVerifyingTheCodeUnlocksTheSession()
    {
        Notification::fake();
        $user = $this->userWithTwoFactor();

        $this->postJson('auth', ['email' => 'admin@example.com', 'password' => 'Secret*12345'])->assertSuccessful();

        $code = $user->verificationCodes()->first()->code;

        $this->postJson('two-factor/verify', ['code' => $code])
            ->assertSuccessful()
            ->assertJsonPath('data.two_factor_verified', true);

        $this->getJson('users')->assertSuccessful();

        // Codes are single use.
        $this->assertDatabaseCount('user_verification_codes', 0);
    }

    public function testAWrongCodeIsRejected()
    {
        Notification::fake();
        $user = $this->userWithTwoFactor();
        $user->createVerificationCode('email');

        $this->be($user)->postJson('two-factor/verify', ['code' => '000000'])
            ->assertJsonValidationErrors(['code']);

        $this->getJson('users')->assertForbidden();
    }

    public function testAnExpiredCodeIsRejected()
    {
        $user = $this->userWithTwoFactor();
        $code = $user->createVerificationCode('email');
        $this->travel(11)->minutes();

        $this->be($user)->postJson('two-factor/verify', ['code' => $code->code])
            ->assertJsonValidationErrors(['code']);
    }

    public function testTheLoginChallengeOnlyAcceptsTheEnabledMethod()
    {
        $user = $this->userWithTwoFactor('sms');
        $emailCode = $user->createVerificationCode('email');

        // An email code can't satisfy an SMS login challenge, and can't switch the method.
        $this->be($user)->postJson('two-factor/verify', ['code' => $emailCode->code, 'method' => 'email'])
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_method_enabled' => 'sms']);
    }

    public function testSettingUpTwoFactorEnablesTheMethodOnceVerified()
    {
        Notification::fake();
        $user = User::factory()->standardUser()->create(['two_factor_required' => null]);

        $this->be($user)->putJson('two-factor', ['two_factor_required' => true, 'two_factor_method_preferred' => 'email'])
            ->assertSuccessful()
            ->assertJsonPath('data.two_factor_method_preferred', 'email')
            ->assertJsonPath('data.two_factor_method_enabled', null);

        $this->postJson('two-factor/email')->assertNoContent();
        Notification::assertSentTo($user, TwoFactorVerificationNotification::class);

        $code = $user->verificationCodes()->first()->code;

        $this->postJson('two-factor/verify', ['code' => $code, 'method' => 'email'])
            ->assertSuccessful()
            ->assertJsonPath('data.two_factor_method_enabled', 'email');
    }

    public function testSmsRequiresAMobileNumber()
    {
        $user = User::factory()->standardUser()->create(['mobile' => null]);

        $this->be($user)->putJson('two-factor', ['two_factor_required' => true, 'two_factor_method_preferred' => 'sms'])
            ->assertJsonValidationErrors(['two_factor_method_preferred']);
    }

    public function testSmsCodesAreRoutedToTheMobileInInternationalFormat()
    {
        $user = User::factory()->make(['mobile' => '0412 345 678']);

        $this->assertSame('61412345678', $user->routeNotificationForVonage());
    }

    public function testTheEmailCodeRendersIntoTheMessage()
    {
        // Notification::fake() never renders the mail, so render it here to catch template errors.
        $user = $this->userWithTwoFactor();
        $code = $user->createVerificationCode('email');

        $html = (string) (new TwoFactorVerificationNotification($code, 'email'))->toMail($user)->render();

        $this->assertStringContainsString($code->formatted_code, $html);
    }
}
