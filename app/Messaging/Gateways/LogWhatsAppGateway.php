<?php

namespace App\Messaging\Gateways;

use App\Messaging\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/**
 * Writes what would have been sent. Keeps the channel exercisable end to end
 * without a WhatsApp Business account.
 */
final class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $to, string $text): void
    {
        Log::info('WhatsApp message (not sent: no gateway configured)', [
            'to' => $to,
            'text' => $text,
        ]);
    }
}
