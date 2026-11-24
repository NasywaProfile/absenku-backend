<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Peta ekstensi yang diizinkan beserta MIME type sah (hasil deteksi server via finfo)
     * dan batas ukuran maksimum dalam byte per kategori.
     */
    private const ALLOWED_MAP = [
        // Gambar (maks 5 MB) — SVG sengaja dilarang untuk mencegah risiko Stored XSS
        'jpg'  => ['category' => 'image', 'max_bytes' => 5242880,  'mimes' => ['image/jpeg', 'image/pjpeg']],
        'jpeg' => ['category' => 'image', 'max_bytes' => 5242880,  'mimes' => ['image/jpeg', 'image/pjpeg']],
        'png'  => ['category' => 'image', 'max_bytes' => 5242880,  'mimes' => ['image/png']],
        'webp' => ['category' => 'image', 'max_bytes' => 5242880,  'mimes' => ['image/webp']],
        'gif'  => ['category' => 'image', 'max_bytes' => 5242880,  'mimes' => ['image/gif']],

        // Video (maks 25 MB)
        'mp4'  => ['category' => 'video', 'max_bytes' => 26214400, 'mimes' => ['video/mp4', 'application/mp4']],
        'webm' => ['category' => 'video', 'max_bytes' => 26214400, 'mimes' => ['video/webm', 'audio/webm']],
        'mov'  => ['category' => 'video', 'max_bytes' => 26214400, 'mimes' => ['video/quicktime']],
        'mkv'  => ['category' => 'video', 'max_bytes' => 26214400, 'mimes' => ['video/x-matroska', 'video/matroska']],

        // Dokumen (maks 15 MB)
        'pdf'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/pdf']],
        'doc'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/msword', 'application/cdfv2', 'application/octet-stream']],
        'docx' => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
        'xls'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/vnd.ms-excel', 'application/cdfv2', 'application/octet-stream']],
        'xlsx' => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']],
        'ppt'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/vnd.ms-powerpoint', 'application/cdfv2', 'application/octet-stream']],
        'pptx' => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip']],
        'zip'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['application/zip', 'application/x-zip-compressed']],
        'csv'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['text/csv', 'text/plain', 'application/csv']],
        'txt'  => ['category' => 'document', 'max_bytes' => 15728640, 'mimes' => ['text/plain']],
    ];

    public function upload(Request $request)
    {
        $user = $request->user();
        if (! $user || strtoupper((string) $user->status) !== 'ACTIVE') {
            return response()->json(['message' => 'Akses upload memerlukan akun aktif yang terautentikasi.'], 403);
        }

        $file = $request->file('file') ?? $request->file('document') ?? $request->file('attachment');
        if (! $file || ! $file->isValid()) {
            return response()->json(['message' => 'Tidak ada berkas valid yang diunggah.'], 400);
        }

        // Validasi double-extension / path traversal dan pastikan ekstensi berada dalam allowlist ketat
        $originalName = basename((string) $file->getClientOriginalName());
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || ! array_key_exists($extension, self::ALLOWED_MAP)) {
            return response()->json([
                'message' => 'Format berkas tidak diizinkan. Hanya gambar (JPG/PNG/WEBP/GIF), video (MP4/WEBM/MOV/MKV), dan dokumen resmi (PDF/Office/ZIP/CSV/TXT) yang diterima.',
            ], 422);
        }

        // Pastikan nama file tidak mengandung ekstensi eksekusi tersembunyi (e.g. shell.php.jpg)
        $dangerousTokens = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'asp', 'aspx', 'jsp', 'cgi', 'pl', 'sh'];
        $nameParts = explode('.', strtolower($originalName));
        if (count($nameParts) > 2) {
            foreach (array_slice($nameParts, 1, -1) as $midExt) {
                if (in_array($midExt, $dangerousTokens, true)) {
                    return response()->json([
                        'message' => 'Nama berkas mengandung ekstensi ganda berbahaya dan ditolak oleh sistem.',
                    ], 422);
                }
            }
        }

        $rule = self::ALLOWED_MAP[$extension];
        $fileSize = (int) $file->getSize();
        if ($fileSize <= 0 || $fileSize > $rule['max_bytes']) {
            $maxMb = (int) ($rule['max_bytes'] / 1048576);
            return response()->json([
                'message' => "Ukuran berkas melebihi batas maksimal ({$maxMb} MB) untuk kategori ini.",
            ], 422);
        }

        // Verifikasi MIME type aktual berdasarkan magic-byte file di server via finfo (bukan ekstensi atau Content-Type klien)
        $detectedMime = '';
        $realPath = $file->getRealPath();
        if (class_exists(\finfo::class) && $realPath && is_file($realPath)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = strtolower((string) $finfo->file($realPath));
        }
        if ($detectedMime === '') {
            $detectedMime = strtolower((string) $file->getMimeType());
        }

        if (! in_array($detectedMime, $rule['mimes'], true)) {
            return response()->json([
                'message' => "Isi berkas ({$detectedMime}) tidak sesuai dengan ekstensi .{$extension}.",
            ], 422);
        }

        // Tolak berkas teks/gambar polyglot yang menyisipkan tag eksekusi PHP atau <script di seluruh isi berkas
        if (in_array($rule['category'], ['image', 'document'], true) && $extension !== 'zip' && $realPath && is_file($realPath)) {
            $handle = @fopen($realPath, 'rb');
            if ($handle !== false) {
                $carry = '';
                while (! feof($handle)) {
                    $chunk = (string) fread($handle, 65536);
                    $window = $carry . $chunk;
                    if (preg_match('/<\?(php|=)|<script\b/i', $window)) {
                        fclose($handle);
                        return response()->json([
                            'message' => 'Berkas mengandung konten skrip berbahaya dan ditolak.',
                        ], 422);
                    }
                    $carry = substr($window, -32);
                }
                fclose($handle);
            }
        }

        $filename = time() . '_' . Str::random(16) . '.' . $extension;
        $path = $file->storeAs('uploads', $filename, 'public');

        $requestHost = rtrim((string) $request->getSchemeAndHttpHost(), '/');
        $configUrl = rtrim((string) config('app.url', ''), '/');
        $isLocalConfig = str_contains($configUrl, 'localhost') || str_contains($configUrl, '127.0.0.1');

        // Prioritaskan host request jika config app.url belum diatur atau masih localhost/127.0.0.1
        $baseUrl = (! empty($requestHost) && ($isLocalConfig || empty($configUrl)))
            ? $requestHost
            : ($configUrl ?: $requestHost);

        $url = rtrim($baseUrl, '/') . '/storage/' . $path;
        $relativeUrl = '/storage/' . $path;

        return response()->json([
            'message' => 'Berkas berhasil diunggah.',
            'url' => $url,
            'relative_url' => $relativeUrl,
            'path' => $path,
            'name' => $originalName,
            'size' => $fileSize,
            'type' => $detectedMime,
        ]);
    }
}
