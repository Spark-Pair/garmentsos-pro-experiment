<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BranchLogoController extends Controller
{
    public function show(Branch $branch): StreamedResponse
    {
        $path = ltrim((string) $branch->logo_path, '/');

        abort_if($path === '' || str_contains($path, '..'), 404);
        abort_unless(str_starts_with($path, 'branch-logos/'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
