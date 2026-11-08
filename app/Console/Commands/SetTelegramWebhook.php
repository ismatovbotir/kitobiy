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
    protected $description = 'Tell Telegram where to send the bot\'s messages (the same as the button on /admin/telegram)';

    public function handle(Telegram $telegram): int
    {
        if (($problem = $telegram->webhookProblem()) !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        if (! $telegram->registerWebhook()) {
            $this->error('Telegram did not accept the webhook. See the log.');

            return self::FAILURE;
        }

        $this->info('Webhook set to '.$telegram->webhookUrl().' for @'.$telegram->botUsername());

        return self::SUCCESS;
    }
}
