<?php

namespace App\Messaging;

use App\Messaging\Contracts\CustomerChannel;
use InvalidArgumentException;

/**
 * The channels this installation knows about, keyed for config and queued jobs.
 */
final class ChannelRegistry
{
    /**
     * @param  array<string, CustomerChannel>  $channels
     */
    public function __construct(private readonly array $channels) {}

    /**
     * @return array<string, CustomerChannel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]);
    }

    public function get(string $key): CustomerChannel
    {
        return $this->channels[$key]
            ?? throw new InvalidArgumentException(sprintf('Unknown notification channel [%s].', $key));
    }
}
