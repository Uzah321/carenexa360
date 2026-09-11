<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * Sends mail through the Microsoft Graph API (POST /users/{mailbox}/sendMail)
 * instead of SMTP — plugs into the existing Mail/Notification system as an
 * ordinary mailer, so every Notification class that builds a MailMessage
 * keeps working unchanged regardless of which transport is configured.
 *
 * Graph only lets you send *as* the mailbox in the URL path (no arbitrary
 * From address the way SMTP allows) — that mailbox is `from_address` below,
 * a real mailbox in the Azure tenant the app is registered against.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $fromAddress,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('The Microsoft Graph transport only supports Email messages.');
        }

        $payload = [
            'message' => [
                'subject' => (string) $email->getSubject(),
                'body' => [
                    'contentType' => $email->getHtmlBody() ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients' => $this->toRecipients($email->getTo()),
                'ccRecipients' => $this->toRecipients($email->getCc()),
                'bccRecipients' => $this->toRecipients($email->getBcc()),
            ],
            'saveToSentItems' => true,
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post("https://graph.microsoft.com/v1.0/users/{$this->fromAddress}/sendMail", $payload);

        if ($response->failed()) {
            throw new TransportException("Microsoft Graph sendMail failed ({$response->status()}): {$response->body()}");
        }
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{emailAddress: array{address: string, name: string}}>
     */
    private function toRecipients(array $addresses): array
    {
        return array_map(fn (Address $address) => [
            'emailAddress' => [
                'address' => $address->getAddress(),
                'name' => $address->getName(),
            ],
        ], $addresses);
    }

    /**
     * Cached just under the token's real lifetime (Graph app-only tokens are
     * valid ~60 minutes) so every outgoing email doesn't cost its own
     * client-credentials round trip to Microsoft's login endpoint.
     */
    private function getAccessToken(): string
    {
        $cacheKey = 'msgraph_access_token_'.md5($this->clientId);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            );

            if ($response->failed()) {
                throw new RuntimeException("Failed to get Microsoft Graph access token: {$response->body()}");
            }

            $token = $response->json('access_token');

            if (! $token) {
                throw new RuntimeException('Microsoft Graph token response did not include an access_token.');
            }

            return $token;
        });
    }

    public function __toString(): string
    {
        return 'msgraph';
    }
}
