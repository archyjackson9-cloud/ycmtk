<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PWA installability - a static manifest/service worker/icon set (not
 * routed through Laravel, so asserted at the filesystem level) plus the
 * <link rel="manifest">/service-worker registration actually present in
 * both the storefront layout and the admin panel's <head> (rendered
 * through Blade/Filament, so these ARE real HTTP assertions).
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_is_valid_and_declares_both_icon_sizes(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertSame('CY-Market', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertCount(2, $manifest['icons']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_the_icon_files_are_real_images_at_the_declared_sizes(): void
    {
        [$width, $height] = getimagesize(public_path('icons/icon-192.png'));
        $this->assertSame([192, 192], [$width, $height]);

        [$width, $height] = getimagesize(public_path('icons/icon-512.png'));
        $this->assertSame([512, 512], [$width, $height]);

        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }

    public function test_the_service_worker_and_offline_page_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));

        // Only ever acts on same-origin GET requests - never a POST, never
        // cross-origin (TOR-adjacent safety: nothing dynamic/session-bearing
        // should ever be cached).
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
    }

    public function test_the_storefront_declares_the_manifest_and_registers_the_service_worker(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
        $response->assertSee("navigator.serviceWorker.register('/sw.js')", false);
    }

    public function test_the_admin_panel_also_declares_the_manifest(): void
    {
        $response = $this->get('/'.config('cymarket.filament_path').'/login');

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
    }
}
