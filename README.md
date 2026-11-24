# DesignOS Blade renderer

`designos/renderer-blade` is a Laravel-oriented PHP renderer for DesignOS `PageDocument` arrays. It deliberately does not depend on the TypeScript editor, compiler, or React/Vue renderers.

## Use

Install the package in a Laravel 13 app, register `RendererBladeServiceProvider` through Composer package discovery, then inject `BladePageRenderer`:

```php
$html = $renderer->render(
    $pageDocument,
    [
        'Page' => 'site.page',
        'hero' => 'site.blocks.hero',
    ],
    [
        'registry' => $componentManifests,
        'context' => ['site' => $site],
    ],
);
```

The component map is trusted host configuration. Never derive view names from a serialized node type. For a mapped type, the Blade view receives `node`, `id`, `type`, `props`, `children`, `slots`, `parentId`, `slotName`, plus the supplied context values. `children` and each `slots[name]` are arrays of rendered child HTML strings, preserving child order and slot identity.

`GeneratedComponentRegistry` loads and validates the `components.json` and `registry.json` artifacts emitted by a DesignOS Blade compiler. Laravel package discovery registers it as a singleton rooted at `resources/views/vendor/<namespace>`, so applications do not need to duplicate generated-registry loading or validation in their own `App\Support` classes.

A single declared slot receives legacy `children` only when the manifest declares exactly one slot, `capabilities.canHaveChildren` is false or omitted, and the node has not supplied that slot explicitly. Default children and explicit named slots remain separate otherwise. The source document is not mutated. An unmapped type falls back to its rendered children and slot content.

Blade templates should use escaped `{{ }}` output for JSON props. Only emit `children` and slot strings as raw HTML when they come from this renderer’s trusted, host-owned component map. The renderer checks document/node shape and enforces depth and node-count limits.

Laravel 13 requires PHP 8.3 or newer. This package requires `illuminate/contracts` and `illuminate/view` 13.x.
