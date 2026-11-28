<?php

declare(strict_types=1);

namespace DesignOS\RendererBlade;

use InvalidArgumentException;

/**
 * Projects a versioned DesignDocument's grouped tokens into safe CSS custom properties.
 *
 * This is renderer infrastructure: consumers provide the independent design document,
 * while the renderer owns token lookup, fallback values, derived values, and CSS safety.
 */
final class DesignThemeCssVariables
{
    private const COLOR_TOKENS = [
        'bg0' => '--color-bg-0',
        'bg1' => '--color-bg-1',
        'bg2' => '--color-bg-2',
        'bg3' => '--color-bg-3',
        'bg4' => '--color-bg-4',
        'bg5' => '--color-bg-5',
        'text0' => '--color-text-0',
        'text1' => '--color-text-1',
        'text2' => '--color-text-2',
        'text3' => '--color-text-3',
        'accent' => '--color-green',
        'accentDim' => '--color-green-dim',
        'borderDefault' => '--color-border-default',
        'borderSubtle' => '--color-border-subtle',
        'borderHover' => '--color-border-hover',
    ];

    private const COLOR_DEFAULTS = [
        'bg0' => '#09090b', 'bg1' => '#0f0f12', 'bg2' => '#18181b',
        'bg3' => '#1e1e23', 'bg4' => '#27272a', 'bg5' => '#303036',
        'text0' => '#fafafa', 'text1' => '#a1a1aa', 'text2' => '#71717a', 'text3' => '#52525b',
        'accent' => '#22c55e', 'accentDim' => '#16a34a',
        'borderDefault' => '#2a2a2f', 'borderSubtle' => '#1f1f24', 'borderHover' => '#3a3a3f',
    ];

    /** @param array<string, mixed> $design A version 1 DesignDocument decoded from JSON. */
    public static function fromDesign(array $design): string
    {
        if (($design['schemaVersion'] ?? null) !== '1' || !is_string($design['siteId'] ?? null) || !is_array($design['tokens'] ?? null)) {
            throw new InvalidArgumentException('Expected a version 1 DesignOS DesignDocument with siteId and tokens.');
        }

        return self::fromTokens($design['tokens']);
    }

    /** @param array<string, mixed> $tokens DesignDocument tokens grouped by category. */
    public static function fromTokens(array $tokens): string
    {
        $theme = array_merge(
            is_array($tokens['colors'] ?? null) ? $tokens['colors'] : [],
            is_array($tokens['fonts'] ?? null) ? $tokens['fonts'] : [],
            is_array($tokens['radii'] ?? null) ? $tokens['radii'] : [],
        );
        $colors = [];
        foreach (self::COLOR_TOKENS as $key => $token) {
            $colors[$key] = self::safeColor($theme[$key] ?? null, self::COLOR_DEFAULTS[$key]);
        }

        $accentHex = ltrim($colors['accent'], '#');
        if (strlen($accentHex) === 3) {
            $accentHex = $accentHex[0] . $accentHex[0] . $accentHex[1] . $accentHex[1] . $accentHex[2] . $accentHex[2];
        }
        $rgb = implode(', ', [
            hexdec(substr($accentHex, 0, 2)),
            hexdec(substr($accentHex, 2, 2)),
            hexdec(substr($accentHex, 4, 2)),
        ]);

        $radius = self::safeRadius($theme['radius'] ?? 8, 8);
        $radiusLg = max($radius, self::safeRadius($theme['radiusLg'] ?? 12, 12));
        $fontSans = self::safeFont($theme['fontSans'] ?? null, 'DM Sans');
        $fontDisplay = self::safeFont($theme['fontDisplay'] ?? null, 'Space Grotesk');
        $fontMono = self::safeFont($theme['fontMono'] ?? null, 'JetBrains Mono');

        $variables = [];
        foreach (self::COLOR_TOKENS as $key => $token) {
            $variables[] = $token . ':' . $colors[$key];
        }
        array_push(
            $variables,
            "--color-green-glow:rgba({$rgb}, 0.12)",
            "--color-green-glow2:rgba({$rgb}, 0.06)",
            "--color-accent-rgb:{$rgb}",
            '--font-sans:"' . $fontSans . '", ui-sans-serif, system-ui, sans-serif',
            '--font-display:"' . $fontDisplay . '", ui-sans-serif, system-ui, sans-serif',
            '--font-mono:"' . $fontMono . '", ui-monospace, monospace',
            '--radius-sm:' . round($radius / 2) . 'px',
            '--radius-md:' . $radius . 'px',
            '--radius-lg:' . $radiusLg . 'px',
            '--radius-xl:' . ($radiusLg + round($radius / 2)) . 'px',
            '--radius-2xl:' . ($radiusLg * 2) . 'px',
        );

        return implode(';', $variables) . ';';
    }

    private static function safeColor(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1
            ? $value
            : $fallback;
    }

    private static function safeRadius(mixed $value, float $fallback): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return $fallback;
        }

        return min(64, max(0, (float) $value));
    }

    private static function safeFont(mixed $value, string $fallback): string
    {
        if (!is_string($value)) {
            return $fallback;
        }

        $font = trim((string) preg_replace('/[^a-zA-Z0-9 _-]/', '', $value));

        return $font === '' ? $fallback : $font;
    }
}
