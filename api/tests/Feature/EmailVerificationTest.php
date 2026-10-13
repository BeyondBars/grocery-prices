<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SignupVerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function testCanSendVerficationEmail(): void
    {
        Notification::fake();

        $response = $this->postJson('verification', ['email' => 'john@email.com']);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Verification email sent']);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            SignupVerifyEmailNotification::class,
            function ($notification, $channels, AnonymousNotifiable $notifiable){
                return $notifiable->routes['mail'] === 'john@email.com';
            }
        );
    }

    public function testShouldNotSendEmailIfEmailAlreadyExistsInUse(): void
    {
        User::factory()->create(['email' => 'john@email.com']);

        $response = $this->postJson('verification', ['email' => 'john@email.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $response->assertJson([
            'message' => 'The email has already been taken.'
        ]);
    } 
}
