<?php

namespace Tests\Feature;

use App\Modules\Marketing\Support\DemoTenant;
use App\Notifications\DemoRequestConfirmationNotification;
use App\Notifications\DemoRequestReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_request_a_demo(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/demo-requests', [
            'name' => 'Jordan Smith',
            'email' => 'jordan@riverside-care.test',
            'organization_name' => 'Riverside Home Care',
            'phone' => '01234 567890',
            'message' => 'Interested in the medications module.',
        ]);

        $response->assertCreated()->assertJsonPath('data.organization_name', 'Riverside Home Care');
        $this->assertDatabaseHas('demo_requests', [
            'email' => 'jordan@riverside-care.test',
            'organization_name' => 'Riverside Home Care',
        ]);
    }

    public function test_the_response_includes_the_shared_demo_login(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/demo-requests', [
            'name' => 'Jordan Smith',
            'email' => 'jordan@riverside-care.test',
            'organization_name' => 'Riverside Home Care',
        ]);

        $response->assertCreated()
            ->assertJsonPath('demo_login.email', DemoTenant::LOGIN_EMAIL)
            ->assertJsonPath('demo_login.password', DemoTenant::LOGIN_PASSWORD);
    }

    public function test_it_notifies_both_the_sales_inbox_and_the_requester(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/demo-requests', [
            'name' => 'Jordan Smith',
            'email' => 'jordan@riverside-care.test',
            'organization_name' => 'Riverside Home Care',
        ])->assertCreated();

        Notification::assertSentOnDemand(
            DemoRequestReceivedNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === config('app.demo_requests_email'),
        );
        Notification::assertSentOnDemand(
            DemoRequestConfirmationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'jordan@riverside-care.test',
        );
    }

    public function test_a_demo_request_requires_the_core_fields(): void
    {
        $response = $this->postJson('/api/v1/demo-requests', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'organization_name']);
    }

    public function test_a_failed_notification_email_does_not_fail_the_request(): void
    {
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('SMTP is down'));

        $response = $this->postJson('/api/v1/demo-requests', [
            'name' => 'Jordan Smith',
            'email' => 'jordan@riverside-care.test',
            'organization_name' => 'Riverside Home Care',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('demo_requests', ['email' => 'jordan@riverside-care.test']);
    }
}
