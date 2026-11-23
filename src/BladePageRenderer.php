<?php

declare(strict_types=1);

namespace DesignOS\RendererBlade;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;

/**
 * Traverses a DesignOS PageDocument and renders each node with a trusted Blade view.
 *
 * The component map is owned by the host application. Serialized node types are
 * never converted into view names, and component templates should escape prop
 * values with Blade's normal {{ }} syntax. Child and named-slot values are HTML
 * returned by those trusted component templates.
 */
final class BladePageRenderer
{
    private const MAX_DEPTH = 128;
    private const MAX_NODES = 10000;

    private int $visitedNodes = 0;

    public function __construct(private readonly ViewFactory $views)
    {
    }

    /**
     * @param array<string, mixed> $document Canonical PageDocument decoded from JSON.
     * @param array<string, string> $components Trusted node type => Blade view map.
     * @param array{registry?: array<string, mixed>, context?: array<string, mixed>} $options
     */
    public function render(array $document, array $components, array $options = []): string
    {
        if (!is_string($document['version'] ?? null) || !is_array($document['root'] ?? null)) {
            throw new InvalidArgumentException('Expected a DesignOS PageDocument with a version and root node.');
        }

        $registry = $options['registry'] ?? [];
        $context = $options['context'] ?? [];
        if (!is_array($registry) || !is_array($context)) {
            throw new InvalidArgumentException('Renderer registry and context options must be arrays.');
        }

        $this->visitedNodes = 0;

        return $this->renderNode($document['root'], $components, $registry, $context, null, null, 0);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, string> $components
     * @param array<string, mixed> $registry
     * @param array<string, mixed> $context
     * @param string|null $parentId
     * @param string|null $slotName
     */
    private function renderNode(
        array $node,
        array $components,
        array $registry,
        array $context,
        ?string $parentId,
        ?string $slotName,
        int $depth,
    ): string {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('PageDocument exceeds the maximum render depth.');
        }
        if (++$this->visitedNodes > self::MAX_NODES) {
            throw new InvalidArgumentException('PageDocument exceeds the maximum render node count.');
        }

        $id = $node['id'] ?? null;
        $type = $node['type'] ?? null;
        $props = $node['props'] ?? [];
        $children = $node['children'] ?? [];
        $explicitSlots = $node['slots'] ?? [];

        if (!is_string($id) || !is_string($type) || $type === '') {
            throw new InvalidArgumentException('Every PageNode must have a string id and non-empty type.');
        }
        if (!is_array($props) || !is_array($children) || !is_array($explicitSlots)) {
            throw new InvalidArgumentException("PageNode '{$id}' has invalid props, children, or slots.");
        }

        foreach ($children as $child) {
            if (!is_array($child)) {
                throw new InvalidArgumentException("PageNode '{$id}' contains a malformed child node.");
            }
        }
        foreach ($explicitSlots as $nodes) {
            if (!is_array($nodes)) {
                throw new InvalidArgumentException("PageNode '{$id}' contains a malformed named slot.");
            }
            foreach ($nodes as $child) {
                if (!is_array($child)) {
                    throw new InvalidArgumentException("PageNode '{$id}' contains a malformed node in a named slot.");
                }
            }
        }

        $manifest = $this->manifestFor($type, $registry);
        $declaredSlots = is_array($manifest['slots'] ?? null) ? $manifest['slots'] : [];
        $declaredNames = [];
        foreach ($declaredSlots as $definition) {
            if (is_array($definition) && is_string($definition['name'] ?? null)) {
                $declaredNames[] = $definition['name'];
            }
        }

        $capabilities = is_array($manifest['capabilities'] ?? null) ? $manifest['capabilities'] : [];
        $canHaveChildren = (bool) ($capabilities['canHaveChildren'] ?? false);
        $legacySlot = null;
        if (
            count($declaredNames) === 1
            && !$canHaveChildren
            && $children !== []
            && !array_key_exists($declaredNames[0], $explicitSlots)
        ) {
            $legacySlot = $declaredNames[0];
        }

        $slotNames = array_values(array_unique([...$declaredNames, ...array_map('strval', array_keys($explicitSlots))]));
        $renderedSlots = [];
        foreach ($slotNames as $name) {
            $slotNodes = $legacySlot === $name ? $children : ($explicitSlots[$name] ?? []);
            $renderedSlots[$name] = [];
            foreach ($slotNodes as $child) {
                $renderedSlots[$name][] = $this->renderNode(
                    $child,
                    $components,
                    $registry,
                    $context,
                    $id,
                    $name,
                    $depth + 1,
                );
            }
        }

        $renderedChildren = [];
        if ($legacySlot === null) {
            foreach ($children as $child) {
                $renderedChildren[] = $this->renderNode(
                    $child,
                    $components,
                    $registry,
                    $context,
                    $id,
                    null,
                    $depth + 1,
                );
            }
        }

        // View paths are selected only from the host's trusted map, never from JSON.
        $view = $components[$type] ?? null;
        if (!is_string($view) || $view === '') {
            $fallback = implode('', $renderedChildren);
            foreach ($renderedSlots as $slotChildren) {
                $fallback .= implode('', $slotChildren);
            }
            return $fallback;
        }

        return $this->views->make($view, array_merge($context, [
            'node' => $node,
            'id' => $id,
            'type' => $type,
            'props' => $props,
            'children' => $renderedChildren,
            'slots' => $renderedSlots,
            'parentId' => $parentId,
            'slotName' => $slotName,
        ]))->render();
    }

    /** @param array<string, mixed> $registry
     *  @return array<string, mixed>
     */
    private function manifestFor(string $type, array $registry): array
    {
        $manifest = $registry[$type] ?? null;
        if (is_array($manifest)) {
            return $manifest;
        }

        // Also accept the list-shaped manifests emitted by compiler targets.
        foreach ($registry as $candidate) {
            if (is_array($candidate) && ($candidate['type'] ?? null) === $type) {
                return $candidate;
            }
        }

        return [];
    }
}
