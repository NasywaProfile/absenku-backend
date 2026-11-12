<?php

namespace App\Support;

class InputSanitizer
{
    /**
     * MIME type allowlist untuk inline base64 Data URL pada lampiran/profil.
     * HTML, SVG, XML, dan JavaScript dilarang keras untuk mencegah Stored XSS.
     */
    private const SAFE_DATA_URL_RE = '/^data:(?:image\/(?:png|jpe?g|webp|gif|bmp|avif)|application\/pdf|video\/(?:mp4|webm|quicktime|x-matroska)|application\/(?:msword|vnd\.openxmlformats-officedocument\.wordprocessingml\.document|vnd\.ms-excel|vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet|vnd\.ms-powerpoint|vnd\.openxmlformats-officedocument\.presentationml\.presentation|zip|x-zip-compressed|octet-stream)|text\/(?:plain|csv))(?:;[a-zA-Z0-9=._+-]+)*;base64,/i';

    /**
     * Memastikan URL lampiran, avatar, atau tautan menggunakan skema yang aman.
     */
    public static function isSafeMediaUrl(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '#') {
            return true;
        }

        // Hapus karakter kontrol ASCII agar skema tersamar (misal java\nscript:) tidak lolos deteksi
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', '', $trimmed) ?? '';
        if ($clean === '' || $clean === '#') {
            return true;
        }

        if (preg_match('/^https?:\/\//i', $clean)) {
            return true;
        }

        // Path relatif pada origin yang sama (tolak protocol-relative //host)
        if (preg_match('/^\/(?!\/)/', $clean)) {
            return true;
        }

        if (preg_match('/^idb:[a-zA-Z0-9._:-]+$/i', $clean)) {
            return true;
        }

        if (preg_match('/^data:/i', $clean)) {
            if (preg_match('/^data:[^,]*(?:html|svg|xml|script|ecmascript)/i', $clean)) {
                return false;
            }
            return preg_match(self::SAFE_DATA_URL_RE, $clean) === 1;
        }

        return false;
    }

    /**
     * Memeriksa apakah payload array/string lampiran/tautan/gambar mengandung URL berbahaya.
     */
    public static function findUnsafeMediaUrl(mixed $payload): ?string
    {
        if (is_string($payload)) {
            return self::isSafeMediaUrl($payload) ? null : $payload;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), ['url', 'href', 'src', 'link', 'data', 'fileurl', 'previewurl'], true)) {
                if (is_string($item) && ! self::isSafeMediaUrl($item)) {
                    return $item;
                }
            } elseif (is_array($item) || is_string($item)) {
                $unsafe = self::findUnsafeMediaUrl($item);
                if ($unsafe !== null) {
                    return $unsafe;
                }
            }
        }

        return null;
    }

    /**
     * Menghapus blok skrip/event-handler berbahaya pada konten HTML email bila berasal dari klien.
     */
    public static function sanitizeEmailHtml(string $html): string
    {
        $sanitized = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $sanitized = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\son[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/(?:javascript|vbscript)\s*:/i', '', $sanitized) ?? $sanitized;

        return $sanitized;
    }
}
