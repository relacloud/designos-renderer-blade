<?php

declare(strict_types=1);

namespace DesignOS\RendererBlade;

use RuntimeException;

final class GeneratedComponentRegistry
{
    public function __construct(private readonly string $viewRoot)
    {
    }

    /** @return array{components: array<string, string>, registry: array<string, mixed>} */
    public function load(): array
    {
        $components = $this->readJson($this->viewRoot . '/components.json');
        $registry = $this->readJson($this->viewRoot . '/registry.json');

        foreach ($components as $type => $view) {
            if (!is_string($type) || !is_string($view) || !str_contains($view, '::')) {
                throw new RuntimeException('Generated DesignOS components registry contains an invalid view mapping.');
            }
            if (preg_match('/^[A-Za-z][A-Za-z0-9._-]*::[A-Za-z][A-Za-z0-9._-]*$/', $view) !== 1 || str_contains($view, '..')) {
                throw new RuntimeException("Generated DesignOS view mapping for '{$type}' is invalid.");
            }
        }

        return ['components' => $components, 'registry' => $registry];
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Generated DesignOS registry is missing: {$path}. Run the Blade compiler.");
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("Generated DesignOS registry is invalid JSON: {$path}", previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException("Generated DesignOS registry must be a JSON object: {$path}");
        }

        return $decoded;
    }
}
