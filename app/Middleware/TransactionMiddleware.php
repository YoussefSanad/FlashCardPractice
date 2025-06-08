<?php

namespace App\Middleware;

use App\Commands\RequiresTransaction;
use Illuminate\Support\Facades\DB;
use League\Tactician\Middleware;

class TransactionMiddleware implements Middleware
{
    public function execute($command, callable $next)
    {
        // Only apply database transactions to commands that explicitly require them
        if (!$command instanceof RequiresTransaction) {
            return $next($command);
        }

        return DB::transaction(callback: function () use ($command, $next) {
            return $next($command);
        });
    }
}
