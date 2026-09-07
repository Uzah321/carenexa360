<?php

namespace Tests\Feature;

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
