<?php

namespace App\Middleware;

use Illuminate\Support\Facades\Log;
use League\Tactician\Middleware;
use Throwable;

class LoggingMiddleware implements Middleware
{
    public function execute($command, callable $next)
    {
        $commandClass = get_class($command);
        $startTime = microtime(true);
        
        Log::info('Command execution started', [
            'command' => $commandClass,
            'data' => $this->getCommandData($command),
        ]);

        try {
            $result = $next($command);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Command executed successfully', [
                'command' => $commandClass,
                'execution_time_ms' => $executionTime,
                'result_type' => is_object($result) ? get_class($result) : gettype($result),
            ]);
            
            return $result;
        } catch (Throwable $exception) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('Command execution failed', [
                'command' => $commandClass,
                'execution_time_ms' => $executionTime,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
            
            throw $exception;
        }
    }

    private function getCommandData($command): array
    {
        $data = [];
        
        // Use reflection to get public readonly properties safely
        $reflection = new \ReflectionClass($command);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            
            // Skip sensitive data
            if (in_array($propertyName, ['password', 'token', 'secret'])) {
                $data[$propertyName] = '[REDACTED]';
                continue;
            }
            
            try {
                $value = $property->getValue($command);
                
                // Truncate long strings for logging
                if (is_string($value) && strlen($value) > 100) {
                    $data[$propertyName] = substr($value, 0, 100) . '...';
                } else {
                    $data[$propertyName] = $value;
                }
            } catch (\Exception $e) {
                $data[$propertyName] = '[UNABLE_TO_READ]';
            }
        }
        
        return $data;
    }
} 