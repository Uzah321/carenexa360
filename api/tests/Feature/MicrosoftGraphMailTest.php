<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MicrosoftGraphMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'msgraph',
            'services.msgraph.tenant_id' => 'test-tenant',
            'services.msgraph.client_id' => 'test-client',
            'services.msgraph.client_secret' => 'test-secret',
            'services.msgraph.from_address' => 'notifications@innovativestart.co.uk',
        ]);
    }

    public function test_sending_mail_fetches_a_token_and_calls_the_graph_sendmail_endpoint(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/sendMail' => Http::response('', 202),
        ]);

        Mail::html('<p>Hello there</p>', function ($message) {
            $message->to('someone@example.com')->subject('Test subject');
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://login.microsoftonline.com/test-tenant/oauth2/v2.0/token'
                && $request['client_id'] === 'test-client'
                && $request['client_secret'] === 'test-secret'
                && $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://graph.microsoft.com/v1.0/users/notifications@innovativestart.co.uk/sendMail') {
                return false;
            }

            $body = $request->data();

            return $body['message']['subject'] === 'Test subject'
                && $body['message']['body']['contentType'] === 'HTML'
                && $body['message']['body']['content'] === '<p>Hello there</p>'
                && $body['message']['toRecipients'][0]['emailAddress']['address'] === 'someone@example.com'
                && $request->hasHeader('Authorization', 'Bearer fake-token');
        });
    }

    public function test_the_access_token_is_cached_across_sends(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/sendMail' => Http::response('', 202),
        ]);

        Mail::html('<p>One</p>', fn ($m) => $m->to('a@example.com')->subject('One'));
        Mail::html('<p>Two</p>', fn ($m) => $m->to('b@example.com')->subject('Two'));

        Http::assertSentCount(3); // one token fetch + two sendMail calls
    }

    public function test_a_failed_graph_send_raises_a_transport_exception(): void
    {
        Http::fake([
            'https://login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'https://graph.microsoft.com/v1.0/users/*/sendMail' => Http::response('Forbidden', 403),
        ]);

        $this->expectException(\Symfony\Component\Mailer\Exception\TransportExceptionInterface::class);

        Mail::html('<p>Hello</p>', fn ($m) => $m->to('someone@example.com')->subject('Test'));
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
