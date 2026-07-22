<?php

namespace App\Console\Commands;

use App\Services\Auth\PasswordResetOtpService;
use Illuminate\Console\Command;

/**
 * Deletes password reset OTPs whose windows have closed.
 *
 * Expired rows are already unusable — verify() and reset() both re-check the
 * timestamps — so this is hygiene, not enforcement: it stops the table growing
 * without bound and limits how many stale hashes a database leak would expose.
 */
class PrunePasswordResetOtps extends Command
{
    protected $signature = 'password-otp:prune';

    protected $description = 'Delete expired password reset OTP records';

    public function handle(PasswordResetOtpService $service): int
    {
        $deleted = $service->pruneExpired();

        $this->info("Pruned {$deleted} expired password reset OTP record(s).");

        return self::SUCCESS;
    }
}
