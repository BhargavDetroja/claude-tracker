<?php

namespace App\Services;

use App\Exceptions\UsageUnavailable;
use Illuminate\Support\Facades\Process;

/**
 * Reads Claude Code's OAuth token out of the macOS Keychain.
 *
 * Claude Code stores a JSON blob as the password of a generic-password item.
 * The first read from a new binary makes macOS raise an access prompt; once
 * the user picks "Always Allow" it is silent from then on.
 */
class ClaudeCredentialStore
{
    /**
     * Absolute path, because the process Electron spawns us from does not
     * reliably inherit a PATH containing /usr/bin.
     */
    private const SECURITY_BINARY = '/usr/bin/security';

    /**
     * `security` exits 44 when the requested item is not in the Keychain.
     */
    private const EXIT_ITEM_NOT_FOUND = 44;

    /**
     * @throws UsageUnavailable
     */
    public function accessToken(): string
    {
        $credentials = $this->readCredentials();

        $token = $credentials['claudeAiOauth']['accessToken'] ?? null;

        if (! is_string($token) || $token === '') {
            throw UsageUnavailable::malformedCredentials();
        }

        if ($this->hasExpired($credentials['claudeAiOauth']['expiresAt'] ?? null)) {
            throw UsageUnavailable::tokenExpired();
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UsageUnavailable
     */
    private function readCredentials(): array
    {
        $service = config('claude.keychain_service');

        $result = Process::run([
            self::SECURITY_BINARY,
            'find-generic-password',
            '-s', $service,
            '-w',
        ]);

        if ($result->exitCode() === self::EXIT_ITEM_NOT_FOUND) {
            throw UsageUnavailable::keychainMissing($service);
        }

        if ($result->failed()) {
            throw UsageUnavailable::keychainUnreadable(
                $service,
                trim($result->errorOutput()) ?: 'Access may have been denied.',
            );
        }

        $decoded = json_decode(trim($result->output()), associative: true);

        if (! is_array($decoded)) {
            throw UsageUnavailable::malformedCredentials();
        }

        return $decoded;
    }

    /**
     * Claude Code records the expiry as milliseconds since the epoch. An
     * expired token is worth catching here because the endpoint's 401 does
     * not distinguish "expired" from "revoked".
     */
    private function hasExpired(mixed $expiresAt): bool
    {
        if (! is_numeric($expiresAt)) {
            return false;
        }

        return ((float) $expiresAt / 1000) <= time();
    }
}
