<?php

namespace Tests\Feature;

use App\Models\EmailSignature;
use App\Models\User;
use App\Services\VerificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateSignatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Asserts that a valid signature returns a 204 response and that the
     * signature is marked as verified in the database.
     *
     * @return void
     */
    public function testValidSignature(): void
    {
        $email = 'hello@hello.com';

        VerificationService::generateSignedUrl('/verify', $email, 30, ['email' => $email]);

        $signature = EmailSignature::where('email', $email)
            ->orderBy('id', 'desc')
            ->first();

        $response = $this->getJson(
            "verify?email={$signature->email}&signature={$signature->signature}&expires={$signature->expires_at}"
        );

        $response->assertStatus(204)->assertNoContent();

        $this->assertDatabaseHas('email_signatures', [
            'id' => $signature->id,
            'verified_at' => Carbon::now(),
        ]);
    }

    /**
     * Tests that the required fields for signature validation return appropriate validation errors.
     *
     * This test verifies that when the 'email', 'expires', and 'signature' fields are missing,
     * the response returns a 422 status with corresponding validation error messages. It also checks
     * for validation errors when an invalid email format is provided.
     */
    public function testRequiredFieldsForValidateSignature()
    {
        $response = $this->getJson("verify");

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email' => 'The email field is required.',
                'expires' => 'The expires field is required.',
                'signature' => 'The signature field is required.',
            ]);

        $response = $this->getJson("verify?email=noexisting@email.com");

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email' => 'The email entered email is invalid.'
            ]);

        $response = $this->getJson("verify?email=noexisting");

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email' => 'The email entered must be a valid email address.'
            ]);
    }


    public function testShowingErrorIfAccountAlreadyExists()
    {
        $email = 'hello@hello.com';
        
        User::factory()->create(['email' => $email]);

        VerificationService::generateSignedUrl('/verify', $email, 30, ['email' => $email]);

        $signature = EmailSignature::where('email', $email)
            ->orderBy('id', 'desc')
            ->first();

        $response = $this->getJson(
            "verify?email={$signature->email}&signature={$signature->signature}&expires={$signature->expires_at}"
        );

        $response->assertStatus(400)->assertJson(['message' => 'An account with this email already exists.']);
    }

    /**
     * Tests that if the verification signature is invalid, the response returns a 400 status code
     * with a message saying that the verification signature was invalid or has expired.
     *
     * @return void
     */
    public function testShowingErrorIfSignatureInvalid()
    {
        $email = 'hello@hello.com';

        VerificationService::generateSignedUrl('/verify', $email, 30, ['email' => $email]);

        $signature = EmailSignature::where('email', $email)
            ->orderBy('id', 'desc')
            ->first();

        $response = $this->getJson(
            "verify?email={$signature->email}&signature=testingwrongsignature&expires={$signature->expires_at}"
        );

        $response->assertStatus(400)->assertJson(['message' => 'Verification signature was invalid or has expired']);
    }

    /**
     * Test that if the email is already verified, we return a 200
     * with a message saying that the email is already verified.
     *
     * @return void
     */
    public function testIfEmailAlreadyVerified()
    {
        $email = 'hello@hello.com';

        VerificationService::generateSignedUrl('/verify', $email, 30, ['email' => $email]);

        $signature = EmailSignature::where('email', $email)
            ->orderBy('id', 'desc')
            ->first();

        $signature->update(['verified_at' => now()]);

        $response = $this->getJson(
            "verify?email={$signature->email}&signature={$signature->signature}&expires={$signature->expires_at}"
        );

        $response->assertStatus(200)->assertJson(['message' => "Email is already verified"]);
    }
}
