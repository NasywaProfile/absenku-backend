<?php

namespace App\Support;

class InputSanitizer
{
    /**
     * MIME type allowlist untuk inline base64 Data URL pada lampiran/profil.
     * HTML, SVG, XML, dan JavaScript dilarang keras untuk mencegah Stored XSS.
     */
    private const SAFE_DATA_URL_RE = '/^data:(?:image\/(?:png|jpe?g|webp|gif|bmp|avif|x-icon)|application\/(?:pdf|x-pdf|cdfv2)|video\/(?:mp4|webm|quicktime|x-matroska)|application\/(?:msword|vnd\.openxmlformats-officedocument\.wordprocessingml\.document|vnd\.ms-excel|vnd\.openxmlformats-officedocument\.spreadsheetml\.sheet|vnd\.ms-powerpoint|vnd\.openxmlformats-officedocument\.presentationml\.presentation|zip|x-zip-compressed|octet-stream|csv)|text\/(?:plain|csv|comma-separated-values))(?:;[a-zA-Z0-9=._+-]+)*;base64,/i';

    /**
     * Nama kunci payload yang nilainya harus divalidasi ketat sebagai URL media/tautan.
     */
    private const URL_FIELD_KEYS = [
        'url',
        'href',
        'src',
        'link',
        'data',
        'fileurl',
        'file_url',
        'previewurl',
        'preview_url',
        'attachmentdata',
        'attachment_data',
        'data_lampiran',
        'uri',
        'foto',
        'avatar',
        'foto_profil',
        'foto_petugas',
    ];

    /**
     * Memastikan string biasa (non-URL seperti nama/id/stage lampiran) tidak menyisipkan skema eksekusi skrip.
     */
    private static function hasDangerousScriptScheme(string $value): bool
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/', '', trim($value)) ?? '';
        if ($clean === '') {
            return false;
        }

        return preg_match(
            '/^\s*(?:javascript|vbscript):|^\s*data:[^,]*(?:html|svg|xml|script|ecmascript)/i',
            $clean
        ) === 1;
    }

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
        return self::walkMediaPayload($payload, true);
    }

    private static function walkMediaPayload(mixed $payload, bool $expectUrl): ?string
    {
        if (is_string($payload)) {
            if ($expectUrl) {
                return self::isSafeMediaUrl($payload) ? null : $payload;
            }

            return self::hasDangerousScriptScheme($payload) ? $payload : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach ($payload as $key => $item) {
            $childExpectsUrl = is_int($key)
                ? true
                : in_array(strtolower((string) $key), self::URL_FIELD_KEYS, true);

            if (is_array($item) || is_string($item)) {
                $unsafe = self::walkMediaPayload($item, $childExpectsUrl);
                if ($unsafe !== null) {
                    return $unsafe;
                }
            }
        }

        return null;
    }

    /**
     * Pangkas panjang string sesuai kapasitas kolom database.
     */
    public static function truncateStr(?string $value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLen);
    }

    /**
     * Validasi URL media/avatar serta batasi panjangnya agar muat pada kolom TEXT MySQL (~65 KB).
     *
     * @return array{0: bool, 1: ?string}
     */
    public static function boundTextMediaUrl(mixed $raw, int $maxBytes = 65000): array
    {
        if (! is_string($raw)) {
            return [true, null];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [true, null];
        }
        if (! self::isSafeMediaUrl($trimmed)) {
            return [false, null];
        }
        if (strlen($trimmed) > $maxBytes) {
            return [true, null];
        }

        return [true, $trimmed];
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
