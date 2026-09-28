<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function product(string $filename): StreamedResponse|Response
    {
        $safeFilename = basename($filename);

        abort_if(
            $safeFilename !== $filename
            || ! preg_match('/^[A-Za-z0-9._-]+$/', $safeFilename),
            404
        );

        $path = 'products/'.$safeFilename;

        abort_unless(
            Storage::disk('public')->exists($path),
            404
        );

        return Storage::disk('public')->response(
            $path,
            $safeFilename,
            [
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }
}
