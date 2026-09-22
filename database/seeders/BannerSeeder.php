<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

/**
 * Seeds the homepage hero with the copy that used to be hardcoded directly
 * in resources/views/storefront/home.blade.php, as a real Banner row so
 * Super Admin / Marketing Admin can edit the headline, subtitle, button,
 * and background image/video from Admin > Marketing > Banners instead of
 * editing Blade. The hero blade falls back to this same copy when no
 * banner row exists at all, so this seeder is optional, not required.
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::updateOrCreate(
            ['position' => 'homepage_hero', 'sort_order' => 0],
            [
                'title' => 'Farm-Fresh Harvest, Delivered to Your Door.',
                'subtitle' => 'Every fruit, vegetable, and grain on CY-Market is harvested directly from our own certified farms. No third-party middlemen — only genuine farm-fresh goodness.',
                'cta_label' => 'Shop Fresh Produce',
                'link_url' => route('products.index'),
                'is_active' => true,
            ]
        );
    }
}
