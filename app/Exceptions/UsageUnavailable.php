<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown whenever a usage reading cannot be produced, for any reason: the
 * Keychain would not give up the token, the token has expired, or the
 * undocumented endpoint answered with something we cannot read.
 *
 * The message is written to be shown to the user in the dropdown, so it
 * should say what to do about it rather than what went wrong internally.
 */
class UsageUnavailable extends Exception
{
    public static function keychainUnreadable(string $service, string $detail): self
    {
        return new self("Couldn't read \"{$service}\" from the Keychain. {$detail}");
    }

    public static function keychainMissing(string $service): self
    {
        return new self("No \"{$service}\" entry in your Keychain. Sign in to Claude Code first.");
    }

    public static function malformedCredentials(): self
    {
        return new self('The Keychain entry did not contain a Claude OAuth token.');
    }

    public static function tokenExpired(): self
    {
        return new self('Your Claude Code token has expired. Open Claude Code to refresh it.');
    }

    public static function unauthorized(): self
    {
        return new self('Claude rejected the token. Open Claude Code to sign in again.');
    }

    public static function endpointFailed(int $status): self
    {
        return new self("The usage endpoint returned HTTP {$status}.");
    }

    public static function endpointUnreachable(string $detail): self
    {
        return new self("Couldn't reach the usage endpoint. {$detail}");
    }

    public static function unrecognisedResponse(): self
    {
        return new self('The usage endpoint returned a shape this app does not recognise.');
    }
}
