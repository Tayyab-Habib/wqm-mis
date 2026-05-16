<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * F-16 — SMS delivery stub.
 *
 * No production SMS gateway is configured in this environment. This service
 * provides a single entry point that:
 *   • logs the message (so QA can observe what would have been sent), and
 *   • returns true/false based on whether a phone number was provided.
 *
 * Swap the body of `send()` for the real gateway integration when one is
 * provisioned — callers don't need to change.
 */
class SmsService
{
    public function send(?string $phone, string $message): bool
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            Log::info('SMS skipped — no phone number', ['message' => $message]);
            return false;
        }

        Log::info('SMS dispatched (stub)', [
            'to'      => $phone,
            'message' => $message,
        ]);

        // Integration point: replace the line above with the real send call.
        return true;
    }
}
