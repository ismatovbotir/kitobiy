<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests render pages without needing built front-end assets.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * A configured bot (with faked Telegram answers), so unconfirmed accounts
     * have to confirm. Without it nobody waits on the confirmation page.
     */
    protected function withTelegramBot(): void
    {
        config([
            'services.telegram.token' => '123456:test-bot-token',
            'services.telegram.bot_username' => 'KitobiyTestBot',
            'services.telegram.webhook_secret' => 'test-webhook-secret',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    }

    /**
     * Make every outgoing email fail the way an unreachable SMTP server does.
     */
    protected function useFailingMailer(): void
    {
        Mail::extend('failing', fn (): AbstractTransport => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP server is unreachable.');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => ['transport' => 'failing'],
        ]);
    }
}
