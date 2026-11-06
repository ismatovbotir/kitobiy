<?php

namespace App\Console\Commands;

use App\Services\Telegram;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    /**
     * @var string
     */
    protected $signature = 'kitobiy:telegram-webhook';

    /**
     * @var string
     */
    protected $description = 'Tell Telegram where to send the bot\'s messages (run once on the live server)';

    public function handle(Telegram $telegram): int
    {
        $secret = config('services.telegram.webhook_secret');

        if (! $telegram->isConfigured() || blank($secret)) {
            $this->error('Set TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME and TELEGRAM_WEBHOOK_SECRET first.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram needs an https address, but this app's address is {$url}");

            return self::FAILURE;
        }

        if ($telegram->setWebhook($url, $secret)?->successful() !== true) {
            $this->error('Telegram did not accept the webhook. See the log.');

            return self::FAILURE;
        }

        $this->info("Webhook set to {$url}");

        return self::SUCCESS;
    }
}
