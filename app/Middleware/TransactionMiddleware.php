<?php

namespace App\Middleware;

use Illuminate\Support\Facades\DB;
use League\Tactician\Middleware;

class TransactionMiddleware implements Middleware
{
    public function execute($command, callable $next)
    {
        return DB::transaction(function () use ($command, $next) {
            return $next($command);
        });
    }
} 