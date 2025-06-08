<?php

namespace App\Middleware;

use Illuminate\Support\Facades\Cache;
use League\Tactician\Middleware;

class IdempotencyMiddleware implements Middleware
{
    private const CACHE_PREFIX = 'idempotency:';
    private const CACHE_TTL = 300; // 5 minutes

    public function execute($command, callable $next)
    {
        // Only apply idempotency to write commands (not queries)
        if (!$this->isWriteCommand($command)) {
            return $next($command);
        }

        $idempotencyKey = $this->generateIdempotencyKey($command);
        $cacheKey = self::CACHE_PREFIX . $idempotencyKey;

        // Check if we've already processed this exact command
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            return unserialize($cachedResult);
        }

        // Execute the command and cache the result
        $result = $next($command);

        // Cache the serialized result for future idempotency checks
        Cache::put($cacheKey, serialize($result), self::CACHE_TTL);

        return $result;
    }

    private function isWriteCommand($command): bool
    {
        // Determine if this is a write command that should be idempotent
        // In this app, commands are write operations, queries are read operations
        $writeCommands = [
            'App\Commands\CreateFlashcard',
            'App\Commands\SubmitAnswer',
        ];

        return in_array(get_class($command), $writeCommands);
    }

    private function generateIdempotencyKey($command): string
    {
        // Create a deterministic hash based on the serialized command
        $commandClass = get_class($command);
        $serializedCommand = serialize($command);

        // Create a hash that uniquely identifies this command execution
        return hash('sha256', $commandClass . ':' . $serializedCommand);
    }
}
