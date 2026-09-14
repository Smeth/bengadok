<?php

namespace Tests\Unit;

use Tests\TestCase;

class ViteFrontendTest extends TestCase
{
    public function test_vite_manifest_contains_app_entry_when_built(): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (! is_file($manifestPath)) {
            $this->markTestSkipped('Manifest Vite absent — exécutez npm run build (CI le fait automatiquement).');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/js/app.ts', $manifest);
    }

    public function test_app_blade_uses_single_vite_entry(): void
    {
        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringContainsString("@vite(['resources/js/app.ts'])", $blade);
        $this->assertStringNotContainsString('$page[\'component\']', $blade);
    }
}
