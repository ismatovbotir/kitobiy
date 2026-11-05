<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
