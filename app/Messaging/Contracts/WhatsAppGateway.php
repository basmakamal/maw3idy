<?php

namespace App\Messaging\Contracts;

/**
 * The HTTP side of WhatsApp, kept behind a contract so the channel can be
 * written, wired and tested without a paid Business API account. The only
 * implementation in this repository logs; a real one would post to the Cloud
 * API and translate its error responses into exceptions.
 */
interface WhatsAppGateway
{
    /**
     * @param  string  $to  E.164 phone number
     */
    public function send(string $to, string $text): void;
}
