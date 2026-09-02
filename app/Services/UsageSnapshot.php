<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use JsonSerializable;

/**
 * An immutable reading of Claude Code's usage limits.
 *
 * Every field the undocumented endpoint returns is treated as optional. A
 * snapshot with a null percentage is still a valid snapshot: it means "the
 * endpoint answered but did not tell us about this window".
 */
readonly class UsageSnapshot implements JsonSerializable
{
    public function __construct(
        public ?float $fiveHourPercent,
        public ?CarbonImmutable $fiveHourResetsAt,
        public ?float $sevenDayPercent,
        public ?CarbonImmutable $sevenDayResetsAt,
        public CarbonImmutable $fetchedAt,
        public bool $isStale = false,
        public ?string $error = null,
    ) {}

    /**
     * Build a snapshot from the raw endpoint payload.
     *
     * Prefers the top-level `five_hour` / `seven_day` objects and falls back to
     * the parallel `limits` array, since the two have disagreed before and
     * either could be the one that survives an API change.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromApiResponse(array $payload): self
    {
        $limits = is_array($payload['limits'] ?? null) ? $payload['limits'] : [];

        return new self(
            fiveHourPercent: self::readPercent($payload['five_hour'] ?? null)
                ?? self::readLimitPercent($limits, 'session'),
            fiveHourResetsAt: self::readResetsAt($payload['five_hour'] ?? null)
                ?? self::readLimitResetsAt($limits, 'session'),
            sevenDayPercent: self::readPercent($payload['seven_day'] ?? null)
                ?? self::readLimitPercent($limits, 'weekly_all'),
            sevenDayResetsAt: self::readResetsAt($payload['seven_day'] ?? null)
                ?? self::readLimitResetsAt($limits, 'weekly_all'),
            fetchedAt: CarbonImmutable::now(),
        );
    }

    /**
     * A snapshot for when we have never managed to read usage at all.
     */
    public static function unavailable(string $error): self
    {
        return new self(
            fiveHourPercent: null,
            fiveHourResetsAt: null,
            sevenDayPercent: null,
            sevenDayResetsAt: null,
            fetchedAt: CarbonImmutable::now(),
            isStale: true,
            error: $error,
        );
    }

    /**
     * Re-mark a previously good reading as stale after a failed poll, keeping
     * the numbers so the UI can show "last known" rather than nothing.
     */
    public function markStale(string $error): self
    {
        return new self(
            fiveHourPercent: $this->fiveHourPercent,
            fiveHourResetsAt: $this->fiveHourResetsAt,
            sevenDayPercent: $this->sevenDayPercent,
            sevenDayResetsAt: $this->sevenDayResetsAt,
            fetchedAt: $this->fetchedAt,
            isStale: true,
            error: $error,
        );
    }

    /**
     * The percentage that drives the menu bar ring and label.
     */
    public function headlinePercent(): ?float
    {
        return match (config('claude.menu_bar_metric')) {
            'seven_day' => $this->sevenDayPercent,
            'highest' => $this->highestPercent(),
            default => $this->fiveHourPercent,
        };
    }

    public function highestPercent(): ?float
    {
        $percentages = array_filter(
            [$this->fiveHourPercent, $this->sevenDayPercent],
            fn (?float $percent): bool => $percent !== null,
        );

        return $percentages === [] ? null : max($percentages);
    }

    /**
     * The text shown next to the ring, e.g. "60%". Falls back to an em dash
     * when we have never had a reading to show.
     */
    public function menuBarLabel(): string
    {
        $percent = $this->headlinePercent();

        if ($percent === null) {
            return '—';
        }

        return round($percent).'%';
    }

    public function tooltip(): string
    {
        if ($this->error !== null && $this->fiveHourPercent === null) {
            return "Claude usage unavailable\n{$this->error}";
        }

        $lines = [
            'Session: '.self::formatPercent($this->fiveHourPercent),
            'Weekly: '.self::formatPercent($this->sevenDayPercent),
        ];

        if ($this->isStale) {
            $lines[] = 'Last updated '.$this->fetchedAt->diffForHumans();
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'five_hour_percent' => $this->fiveHourPercent,
            'five_hour_resets_at' => $this->fiveHourResetsAt?->toIso8601String(),
            'seven_day_percent' => $this->sevenDayPercent,
            'seven_day_resets_at' => $this->sevenDayResetsAt?->toIso8601String(),
            'fetched_at' => $this->fetchedAt->toIso8601String(),
            'is_stale' => $this->isStale,
            'error' => $this->error,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fiveHourPercent: isset($data['five_hour_percent']) ? (float) $data['five_hour_percent'] : null,
            fiveHourResetsAt: self::parseDate($data['five_hour_resets_at'] ?? null),
            sevenDayPercent: isset($data['seven_day_percent']) ? (float) $data['seven_day_percent'] : null,
            sevenDayResetsAt: self::parseDate($data['seven_day_resets_at'] ?? null),
            fetchedAt: self::parseDate($data['fetched_at'] ?? null) ?? CarbonImmutable::now(),
            isStale: (bool) ($data['is_stale'] ?? false),
            error: $data['error'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function formatPercent(?float $percent): string
    {
        return $percent === null ? 'unknown' : round($percent).'%';
    }

    /**
     * The endpoint reports utilization as a 0-100 float. Anything outside that
     * range is clamped rather than trusted.
     */
    private static function readPercent(mixed $window): ?float
    {
        if (! is_array($window) || ! is_numeric($window['utilization'] ?? null)) {
            return null;
        }

        return self::clamp((float) $window['utilization']);
    }

    private static function readResetsAt(mixed $window): ?CarbonImmutable
    {
        return is_array($window) ? self::parseDate($window['resets_at'] ?? null) : null;
    }

    /**
     * @param  array<int, mixed>  $limits
     */
    private static function readLimitPercent(array $limits, string $kind): ?float
    {
        $limit = self::findLimit($limits, $kind);

        return is_numeric($limit['percent'] ?? null) ? self::clamp((float) $limit['percent']) : null;
    }

    /**
     * @param  array<int, mixed>  $limits
     */
    private static function readLimitResetsAt(array $limits, string $kind): ?CarbonImmutable
    {
        return self::parseDate(self::findLimit($limits, $kind)['resets_at'] ?? null);
    }

    /**
     * @param  array<int, mixed>  $limits
     * @return array<string, mixed>
     */
    private static function findLimit(array $limits, string $kind): array
    {
        foreach ($limits as $limit) {
            if (is_array($limit) && ($limit['kind'] ?? null) === $kind) {
                return $limit;
            }
        }

        return [];
    }

    private static function clamp(float $percent): float
    {
        return max(0.0, min(100.0, $percent));
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
