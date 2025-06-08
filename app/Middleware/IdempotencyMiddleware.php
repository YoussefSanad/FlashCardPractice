<?php

namespace App\Middleware;

use Illuminate\Support\Facades\Cache;
use League\Tactician\Middleware;

class IdempotencyMiddleware implements Middleware
{
    private const CACHE_PREFIX = 'idempotency:';
    private const CACHE_TTL = 3600; // 1 hour in seconds

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
            return $this->deserializeResult($cachedResult);
        }

        // Execute the command and cache the result
        $result = $next($command);

        // Cache the result for future idempotency checks
        Cache::put($cacheKey, $this->serializeResult($result), self::CACHE_TTL);

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
        // Create a deterministic hash based on command class and data
        $commandClass = get_class($command);
        $commandData = $this->extractCommandData($command);

        // Create a hash that uniquely identifies this command execution
        $dataString = serialize([$commandClass, $commandData]);
        return hash('sha256', $dataString);
    }

    private function extractCommandData($command): array
    {
        $data = [];

        // Use reflection to get all public properties
        $reflection = new \ReflectionClass($command);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            try {
                $value = $property->getValue($command);

                // For idempotency, we need exact values
                $data[$propertyName] = $value;
            } catch (\Exception $e) {
                // If we can't read a property, include its name to ensure uniqueness
                $data[$propertyName] = '[UNREADABLE]';
            }
        }

        // Sort to ensure consistent hashing regardless of property order
        ksort($data);

        return $data;
    }

    private function serializeResult($result): array
    {
        if (is_object($result)) {
            return [
                'type' => get_class($result),
                'data' => $this->extractResultData($result),
                'timestamp' => now()->toISOString(),
            ];
        } else {
            // Handle non-object results (arrays, primitives, etc.)
            return [
                'type' => 'primitive',
                'data' => $result,
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    private function deserializeResult(array $cachedData)
    {
        $className = $cachedData['type'];
        $data = $cachedData['data'];

        // Handle primitive types (arrays, strings, numbers, etc.)
        if ($className === 'primitive') {
            return $data;
        }

        // For Eloquent models, we can recreate the instance
        if (is_subclass_of($className, \Illuminate\Database\Eloquent\Model::class)) {
            $model = new $className();

            // Set the attributes and mark as existing (not new)
            $model->setRawAttributes($data);
            $model->exists = true;
            $model->syncOriginal();

            return $model;
        }

        // For other objects, try to create an instance with the data
        try {
            $reflection = new \ReflectionClass($className);
            $instance = $reflection->newInstanceWithoutConstructor();

            foreach ($data as $property => $value) {
                if ($reflection->hasProperty($property)) {
                    $prop = $reflection->getProperty($property);
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $value);
                }
            }

            return $instance;
        } catch (\Exception $e) {
            // If deserialization fails, return the raw data
            return $data;
        }
    }

    private function extractResultData($result): array
    {
        if (is_object($result)) {
            // For Eloquent models, get the attributes
            if ($result instanceof \Illuminate\Database\Eloquent\Model) {
                return $result->getAttributes();
            }

            // For other objects, try to extract public properties
            $data = [];
            $reflection = new \ReflectionClass($result);
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            foreach ($properties as $property) {
                try {
                    $data[$property->getName()] = $property->getValue($result);
                } catch (\Exception $e) {
                    // Skip unreadable properties
                }
            }

            return $data;
        }

        // For non-objects, return as-is
        return ['value' => $result];
    }
}
