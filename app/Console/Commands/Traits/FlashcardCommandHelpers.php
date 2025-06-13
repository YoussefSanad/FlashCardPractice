<?php

namespace App\Console\Commands\Traits;

trait FlashcardCommandHelpers
{
    /**
     * Truncate text to specified length
     */
    protected function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
} 