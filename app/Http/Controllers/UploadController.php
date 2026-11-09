<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function upload(Request $request)
    {
        $file = $request->file('file') ?? $request->file('document') ?? $request->file('attachment');
        if (! $file) {
            return response()->json(['message' => 'Tidak ada berkas yang diunggah.'], 400);
        }

        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . Str::random(8) . ($extension ? '.' . $extension : '');
        $path = $file->storeAs('uploads', $filename, 'public');

        $baseUrl = config('app.url', 'https://in-time.duckdns.org');
        $url = rtrim($baseUrl, '/') . '/storage/' . $path;

        return response()->json([
            'message' => 'Berkas berhasil diunggah.',
            'url' => $url,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'type' => $file->getClientMimeType(),
        ]);
    }
}
