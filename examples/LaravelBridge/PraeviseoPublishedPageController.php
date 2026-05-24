<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PraeviseoPublishedPage;
use Illuminate\Contracts\View\View;

class PraeviseoPublishedPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = PraeviseoPublishedPage::query()
            ->where('slug', $slug)
            ->where('publication_state', 'published')
            ->firstOrFail();

        abort_if($page->is_noindex, 404);

        return view('praeviseo.published-page', compact('page'));
    }
}
