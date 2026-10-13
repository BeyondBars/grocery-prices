<?php

namespace Tests\Feature\Auth;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Support\Enums\UserStatus;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerificationTest extends TestCase
{
    use RefreshDatabase,
        InteractsWithTime;

    public function testVerificationFailsWhenSignatureDoesNotMatch()
    {
        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser();
        $signature = hash_hmac('sha256', 'bad', 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $this->availableAt(now()->addMinutes(60)),
            'identifier' => base64_encode($user->email),
        ]);
        $response->assertForbidden();
        $response->assertJson(['message' => Lang::get('app.email_signature_invalid')]);
    }

    public function testVerificationFailsWhenSignatureMatchesButIsExpired()
    {
        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $expires = $this->availableAt(now()->subMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode($user->email),
        ]);
        $response->assertForbidden();
        $response->assertJson(['message' => Lang::get('app.email_signature_invalid')]);
    }

    public function testVerificationSucceedsWithValidSignature()
    {
        Carbon::setTestNow('2020-06-11 12:00:00');

        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->be($user)->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode($user->email),
        ]);
        $response->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email_verified_at' => '2020-06-11 12:00:00', 'status' => UserStatus::Enabled->value]);
    }

    public function testVerificationSucceedsWhenUnauthenticated()
    {
        Carbon::setTestNow('2020-06-11 12:00:00');

        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode($user->email)
        ]);
        $response->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email_verified_at' => '2020-06-11 12:00:00', 'status' => UserStatus::Enabled->value]);
    }

    public function testVerificationFailsWhenUnauthenticatedAndEmailDoesNotExist()
    {
        Carbon::setTestNow('2020-06-11 12:00:00');

        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $expires = $this->availableAt(now()->addMinutes(60));
        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode('test12345@hello.com')
        ]);
        $response->assertNotFound();
    }

    public function testVerificationFailsWhenUnauthenticatedAndEmailIsIncorrect()
    {
        Carbon::setTestNow('2020-06-11 12:00:00');

        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $user2 = $this->createStandardUser(['email_verified_at' => null]);

        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode($user2->email)
        ]);
        $response->assertForbidden();
    }

    public function testVerificationFailsWhenAlreadyVerified()
    {
        Carbon::setTestNow('2020-06-11 12:00:00');

        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => now()]);

        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $signature = hash_hmac('sha256', implode('.', $parameters), 'key');

        $response = $this->postJson("/auth/verification", [
            'signature' => $signature,
            'expires' => $expires,
            'identifier' => base64_encode($user->email)
        ]);
        $response->assertStatus(400);
        $response->assertJson(['message' => Lang::get('app.email_already_verified')]);
    }

    public function testVerificationDoesNotLogTheUserIn()
    {
        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email_verified_at' => null]);
        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $this->postJson('/auth/verification', [
            'signature' => hash_hmac('sha256', implode('.', $parameters), 'key'),
            'expires' => $expires,
            'identifier' => base64_encode($user->email),
        ])->assertSuccessful();

        $this->assertGuest('web');
    }

    public function testVerifyingANewEmailSwapsItIn()
    {
        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email' => 'old@example.com', 'new_email' => 'new@example.com']);
        $expires = $this->availableAt(now()->addMinutes(60));

        $parameters = $user->emailVerificationSignatureParameters('new@example.com') + compact('expires');
        ksort($parameters);

        $this->postJson('/auth/verification', [
            'signature' => hash_hmac('sha256', implode('.', $parameters), 'key'),
            'expires' => $expires,
            'identifier' => base64_encode('new@example.com'),
        ])->assertNoContent();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com', 'new_email' => null]);
    }

    public function testASignatureForTheCurrentEmailCannotVerifyANewEmail()
    {
        URL::setKeyResolver(fn () => 'key');

        $user = $this->createStandardUser(['email' => 'old@example.com', 'new_email' => 'new@example.com']);
        $expires = $this->availableAt(now()->addMinutes(60));

        // Signed for the old address, presented for the new one.
        $parameters = $user->emailVerificationSignatureParameters() + compact('expires');
        ksort($parameters);

        $this->postJson('/auth/verification', [
            'signature' => hash_hmac('sha256', implode('.', $parameters), 'key'),
            'expires' => $expires,
            'identifier' => base64_encode('new@example.com'),
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'old@example.com']);
    }
}
