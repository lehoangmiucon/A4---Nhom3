<?php

namespace App\Console\Commands;

use App\Services\PurgeExpiredUnverifiedAccounts;
use Illuminate\Console\Command;

class PurgeExpiredUnverifiedAccountsCommand extends Command
{
    protected $signature = 'auth:purge-expired-unverified';

    protected $description = 'Delete student accounts that remain unverified for 24 hours.';

    public function handle(PurgeExpiredUnverifiedAccounts $purger): int
    {
        $deleted = $purger->handle();
        $this->info("Deleted {$deleted} expired unverified account(s).");

        return self::SUCCESS;
    }
}
