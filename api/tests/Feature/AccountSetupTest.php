<?php

namespace Tests\Feature;

use App\Models\EmailSignature;
use App\Models\User;
use App\Support\Enums\UserRole;
use App\Support\Enums\UserStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'john@email.com',
            'mobile' => '0412 345 678',
            'password' => 'Secret*12345',
            'password_confirmation' => 'Secret*12345',
            'agree_terms' => true,
        ], $overrides);
    }

    public function testCanRegisterAfterVerifyingTheEmail()
    {
        Carbon::setTestNow('2024-01-01 10:00:00');
        EmailSignature::factory()->create(['email' => 'john@email.com', 'verified_at' => now()]);

        $this->postJson('account-setup', $this->payload())->assertNoContent();

        $this->assertDatabaseHas('users', [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'john@email.com',
            'status' => UserStatus::Enabled->value,
            'terms_accepted_at' => Carbon::now(),
            'email_verified_at' => Carbon::now(),
        ]);

        $user = User::where('email', 'john@email.com')->first();

        $this->assertTrue($user->isA(UserRole::User->value));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseMissing('email_signatures', ['email' => 'john@email.com']);
    }

    public function testCannotRegisterWithoutAVerifiedEmail()
    {
        $this->postJson('account-setup', $this->payload())->assertForbidden();

        EmailSignature::factory()->create(['email' => 'john@email.com', 'verified_at' => null]);

        $this->postJson('account-setup', $this->payload())->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'john@email.com']);
    }

    public function testCanValidateIfEmailAlreadyExists()
    {
        User::factory()->create(['email' => 'john@email.com']);

        $this->postJson('account-setup', ['email' => 'john@email.com'])
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);

        $this->postJson('account-setup', ['email' => 'john'])
            ->assertJsonValidationErrors(['email' => 'The email field must be a valid email address.']);

        $this->postJson('account-setup', ['email' => str_repeat('A', 256).'@email.com'])
            ->assertJsonValidationErrors(['email' => 'The email field must not be greater than 255 characters.']);
    }

    public function testMobileMustBeAnAustralianMobile()
    {
        EmailSignature::factory()->create(['email' => 'john@email.com', 'verified_at' => now()]);

        $this->postJson('account-setup', $this->payload(['mobile' => '12345']))
            ->assertJsonValidationErrors(['mobile']);
    }
}
