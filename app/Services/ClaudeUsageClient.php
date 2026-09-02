<?php

namespace App\Services;

use App\Exceptions\UsageUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the undocumented endpoint Claude Code uses for its own /usage
 * display.
 *
 * This is not a published API. It has no versioning promise, no deprecation
 * window, and no guarantee that any given field exists. Everything here is
 * written to fail into a clear message rather than an exception the user
 * cannot act on.
 */
class ClaudeUsageClient
{
    public function __construct(
        private readonly ClaudeCredentialStore $credentials,
    ) {}

    /**
     * @throws UsageUnavailable
     */
    public function fetch(): UsageSnapshot
    {
        $token = $this->credentials->accessToken();

        try {
            $response = Http::withToken($token)
                ->withHeaders(['anthropic-beta' => config('claude.oauth_beta')])
                ->timeout(config('claude.request_timeout'))
                ->acceptJson()
                ->get(config('claude.usage_endpoint'));
        } catch (ConnectionException $e) {
            throw UsageUnavailable::endpointUnreachable($e->getMessage());
        }

        if ($response->unauthorized() || $response->forbidden()) {
            throw UsageUnavailable::unauthorized();
        }

        if ($response->failed()) {
            throw UsageUnavailable::endpointFailed($response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw UsageUnavailable::unrecognisedResponse();
        }

        $snapshot = UsageSnapshot::fromApiResponse($payload);

        /**
         * A 200 that yields neither window means the response shape moved.
         * Treating that as a failure keeps the last good reading on screen
         * instead of silently replacing it with zeroes.
         */
        if ($snapshot->fiveHourPercent === null && $snapshot->sevenDayPercent === null) {
            throw UsageUnavailable::unrecognisedResponse();
        }

        return $snapshot;
    }
}
