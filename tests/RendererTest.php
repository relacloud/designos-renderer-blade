<?php

declare(strict_types=1);

namespace Tests;

use DesignOS\RendererBlade\BladePageRenderer;
use DesignOS\RendererBlade\GeneratedComponentRegistry;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RendererTest extends TestCase
{
    private string $viewRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewRoot = sys_get_temp_dir() . '/designos-renderer-' . bin2hex(random_bytes(6));
        mkdir($this->viewRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->viewRoot . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->viewRoot);
        parent::tearDown();
    }

    public function test_registry_accepts_nested_laravel_view_paths(): void
    {
        $this->writeRegistry([
            'hero' => 'designos::components/Hero',
        ]);

        self::assertSame(
            'designos::components/Hero',
            (new GeneratedComponentRegistry($this->viewRoot))->load()['components']['hero'],
        );
    }

    public function test_registry_rejects_traversal_in_view_paths(): void
    {
        $this->writeRegistry([
            'hero' => 'designos::components/../Secret',
        ]);

        $this->expectException(RuntimeException::class);
        (new GeneratedComponentRegistry($this->viewRoot))->load();
    }

    public function test_renderer_projects_legacy_children_into_a_declared_slot(): void
    {
        $view = $this->createMock(View::class);
        $view->expects(self::once())
            ->method('render')
            ->willReturn('<hero><slot>child-html</slot></hero>');

        $factory = $this->createMock(ViewFactory::class);
        $factory->expects(self::once())
            ->method('make')
            ->with(
                'designos::components/Hero',
                self::callback(static function (array $data): bool {
                    return $data['id'] === 'hero-1'
                        && $data['slots']['content'] === [''];
                }),
            )
            ->willReturn($view);

        $renderer = new BladePageRenderer($factory);
        $html = $renderer->render(
            [
                'version' => '1.0',
                'root' => [
                    'id' => 'hero-1',
                    'type' => 'hero',
                    'props' => [],
                    'children' => [[
                        'id' => 'text-1',
                        'type' => 'text',
                        'props' => ['value' => 'ignored by fake view'],
                        'children' => [],
                    ]],
                ],
            ],
            ['hero' => 'designos::components/Hero'],
            [
                'registry' => [
                    'hero' => [
                        'capabilities' => ['canHaveChildren' => false],
                        'slots' => [['name' => 'content']],
                    ],
                ],
            ],
        );

        self::assertSame('<hero><slot>child-html</slot></hero>', $html);
    }

    public function test_renderer_falls_back_to_children_for_an_unmapped_type(): void
    {
        $childView = $this->createMock(View::class);
        $childView->method('render')->willReturn('child-html');

        $factory = $this->createMock(ViewFactory::class);
        $factory->expects(self::once())
            ->method('make')
            ->with('designos::components/Child')
            ->willReturn($childView);

        $html = (new BladePageRenderer($factory))->render(
            [
                'version' => '1.0',
                'root' => [
                    'id' => 'root-1',
                    'type' => 'unknown',
                    'props' => [],
                    'children' => [[
                        'id' => 'child-1',
                        'type' => 'child',
                        'props' => [],
                        'children' => [],
                    ]],
                ],
            ],
            ['child' => 'designos::components/Child'],
        );

        self::assertSame('child-html', $html);
    }

    public function test_renderer_rejects_malformed_documents(): void
    {
        $factory = $this->createMock(ViewFactory::class);

        $this->expectException(InvalidArgumentException::class);
        (new BladePageRenderer($factory))->render(['root' => []], []);
    }

    /** @param array<string, string> $components */
    private function writeRegistry(array $components): void
    {
        file_put_contents($this->viewRoot . '/components.json', json_encode($components, JSON_THROW_ON_ERROR));
        file_put_contents($this->viewRoot . '/registry.json', '{}');
    }
}
