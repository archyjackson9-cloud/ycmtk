<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, Category $category, ProductController $products)
    {
        abort_unless($category->is_active, 404);

        $request->query->set('category', $category->slug);

        return $products->index($request);
    }
}
