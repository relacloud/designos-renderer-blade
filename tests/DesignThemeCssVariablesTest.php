<?php

declare(strict_types=1);

namespace Tests;

use DesignOS\RendererBlade\DesignThemeCssVariables;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DesignThemeCssVariablesTest extends TestCase
{
    public function test_projects_grouped_design_tokens_to_css_custom_properties(): void
    {
        $css = DesignThemeCssVariables::fromDesign([
            'schemaVersion' => '1',
            'siteId' => 'example',
            'tokens' => [
                'colors' => [
                    'accent' => '#abc',
                    'bg0' => '#123456',
                    'text0' => 'javascript:alert(1)',
                ],
                'fonts' => [
                    'fontSans' => 'Inter; color:red',
                    'fontDisplay' => 'Display"; --unsafe: yes',
                    'fontMono' => 'Mono',
                ],
                'radii' => ['radius' => 10, 'radiusLg' => 4],
            ],
        ]);

        self::assertStringContainsString('--color-bg-0:#123456', $css);
        self::assertStringContainsString('--color-green:#abc', $css);
        self::assertStringContainsString('--color-accent-rgb:170, 187, 204', $css);
        self::assertStringContainsString('--color-green-glow:rgba(170, 187, 204, 0.12)', $css);
        self::assertStringContainsString('--color-text-0:#fafafa', $css);
        self::assertStringContainsString('--font-sans:"Inter colorred", ui-sans-serif, system-ui, sans-serif', $css);
        self::assertStringContainsString('--font-display:"Display --unsafe yes", ui-sans-serif, system-ui, sans-serif', $css);
        self::assertStringContainsString('--radius-md:10px', $css);
        self::assertStringContainsString('--radius-lg:10px', $css);
        self::assertStringContainsString('--radius-xl:15px', $css);
        self::assertStringContainsString('--radius-2xl:20px', $css);
        self::assertStringNotContainsString('<', $css);
        self::assertStringNotContainsString('; --', $css);
    }

    public function test_uses_defaults_when_tokens_are_missing_or_invalid(): void
    {
        $css = DesignThemeCssVariables::fromTokens([
            'colors' => ['accent' => '#12ab34ff'],
            'radii' => ['radius' => -10, 'radiusLg' => 100],
        ]);

        self::assertStringContainsString('--color-green:#22c55e', $css);
        self::assertStringContainsString('--font-mono:"JetBrains Mono", ui-monospace, monospace', $css);
        self::assertStringContainsString('--radius-md:0px', $css);
        self::assertStringContainsString('--radius-lg:64px', $css);
    }

    public function test_empty_tokens_return_the_default_theme(): void
    {
        $css = DesignThemeCssVariables::fromTokens([]);

        self::assertStringContainsString('--color-bg-0:#09090b', $css);
        self::assertStringContainsString('--color-green:#22c55e', $css);
        self::assertStringContainsString('--font-sans:"DM Sans", ui-sans-serif, system-ui, sans-serif', $css);
        self::assertStringContainsString('--radius-md:8px', $css);
    }

    public function test_rejects_an_unsupported_design_document_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DesignThemeCssVariables::fromDesign([
            'schemaVersion' => '2',
            'siteId' => 'example',
            'tokens' => [],
        ]);
    }
}
