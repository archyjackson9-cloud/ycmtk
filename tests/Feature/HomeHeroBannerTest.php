<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroBannerTest extends TestCase
{
    use RefreshDatabase;

    private function banner(array $attrs = []): Banner
    {
        return Banner::create($attrs + [
            'title' => 'Banner',
            'position' => 'homepage_hero',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_every_live_hero_banner_is_rendered_not_just_the_first(): void
    {
        $this->banner(['title' => 'Seeded hero one']);
        $this->banner(['title' => 'Newly added hero two', 'subtitle' => 'Sub two', 'cta_label' => 'Go two', 'image' => 'banners/two.jpg']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Seeded hero one')
            ->assertSee('Newly added hero two')
            ->assertSee('Sub two')
            ->assertSee('Go two')
            ->assertSee('storage/banners/two.jpg', false);
    }

    public function test_inactive_expired_and_future_banners_are_hidden(): void
    {
        $this->banner(['title' => 'Live one']);
        $this->banner(['title' => 'Inactive one', 'is_active' => false]);
        $this->banner(['title' => 'Expired one', 'expires_at' => now()->subDay()]);
        $this->banner(['title' => 'Future one', 'starts_at' => now()->addDay()]);

        $this->get('/')
            ->assertSee('Live one')
            ->assertDontSee('Inactive one')
            ->assertDontSee('Expired one')
            ->assertDontSee('Future one');
    }

    public function test_falls_back_to_default_copy_without_banners(): void
    {
        $this->get('/')->assertOk()->assertSee('Farm-Fresh Harvest, Delivered to Your Door.');
    }
}
