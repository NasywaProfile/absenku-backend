<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailController extends Controller
{
    /**
     * Endpoint HTTP pengiriman email notifikasi (terautentikasi & anti open-relay)
     */
    public function sendEmail(Request $request)
    {
        $user = $request->user();
        if (! $user || strtoupper((string) $user->status) !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya pengguna aktif yang dapat mengirim notifikasi email.',
            ], 403);
        }

        $validated = $request->validate([
            'to' => 'required|email:rfc|max:255',
            'subject' => 'required|string|max:255',
            'html' => 'nullable|string|max:100000',
            'text' => 'nullable|string|max:50000',
        ]);

        $to = strtolower(trim($validated['to']));
        $callerRole = strtoupper((string) $user->peran);

        // Cegah penyalahgunaan sebagai Open Relay:
        // Karyawan non-Admin hanya boleh mengirim notifikasi ke alamat email pengguna terdaftar atau email sistem resmi.
        if (! in_array($callerRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            $systemFrom = strtolower((string) config('mail.from.address', ''));
            $isRegisteredRecipient = User::whereRaw('LOWER(email) = ?', [$to])->exists();

            if (! $isRegisteredRecipient && $to !== $systemFrom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengiriman email ke alamat eksternal tidak diizinkan.',
                ], 403);
            }
        }

        $result = $this->dispatchEmail([
            'to' => $validated['to'],
            'subject' => $validated['subject'],
            'html' => $validated['html'] ?? '',
            'text' => $validated['text'] ?? '',
        ]);

        return response()->json(
            $result['body'],
            $result['status']
        );
    }

    /**
     * Dispatcher email internal untuk platform in-Time.
     * Membaca konfigurasi melalui config() agar aman saat config:cache aktif di produksi.
     */
    public function dispatchEmail(array $payload): array
    {
        $to = trim((string) ($payload['to'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? ''));
        $html = (string) ($payload['html'] ?? '');
        $text = (string) ($payload['text'] ?? '');

        if ($to === '' || $subject === '') {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Alamat email tujuan (to) dan subjek (subject) wajib diisi.',
                ],
            ];
        }

        // Hormati mode testing / log (misal MAIL_MAILER=array di phpunit.xml) agar pengujian tidak mengirim email sungguhan
        $defaultMailer = strtolower((string) config('mail.default', 'smtp'));
        if (in_array($defaultMailer, ['array', 'log'], true)) {
            Log::info("[EmailController] Simulasi pengiriman email ({$defaultMailer}) ke {$to}: {$subject}");
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Email notifikasi berhasil dicatat pada mode ' . $defaultMailer . ' untuk ' . $to,
                ],
            ];
        }

        $sent = false;
        $errors = [];

        $smtpHost = (string) (config('mail.mailers.smtp.host') ?: 'smtp.gmail.com');
        $rawUser = config('mail.mailers.smtp.username');
        $smtpUser = (! empty($rawUser) && $rawUser !== 'null') ? (string) $rawUser : '';
        $rawPassword = config('mail.mailers.smtp.password');
        $smtpPassword = (! empty($rawPassword) && $rawPassword !== 'null') ? (string) $rawPassword : '';

        $rawFromName = config('mail.from.name', config('app.name', 'in-Time'));
        $fromName = (empty($rawFromName) || strtolower(trim((string) $rawFromName)) === 'laravel')
            ? 'in-Time'
            : trim((string) $rawFromName);

        $rawAddress = config('mail.from.address');
        $fromAddress = (! empty($rawAddress) && $rawAddress !== 'null' && $rawAddress !== 'hello@example.com')
            ? (string) $rawAddress
            : $smtpUser;

        // 1. OPSI UTAMA: RESEND REST API
        $resendKey = config('services.resend.key');
        if (! empty($resendKey)) {
            try {
                $fromEmail = (string) (config('services.resend.from_email') ?: 'onboarding@resend.dev');

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$resendKey}",
                    'Content-Type' => 'application/json',
                ])->post('https://api.resend.com/emails', [
                    'from' => "{$fromName} <{$fromEmail}>",
                    'to' => [$to],
                    'subject' => $subject,
                    'html' => $html ?: $text,
                    'text' => $text ?: strip_tags($html),
                ]);

                if ($response->successful()) {
                    $sent = true;
                    Log::info("[EmailController] Email berhasil terkirim ke {$to} via Resend API");
                } else {
                    $errors[] = 'Resend API: ' . $response->body();
                    Log::warning('[EmailController] Resend API gagal: ' . $response->body());
                }
            } catch (\Throwable $e) {
                $errors[] = 'Resend Exception: ' . $e->getMessage();
            }
        }

        // 2. OPSI FALLBACK: SYMFONY ESMTP TRANSPORT (SMTP)
        if (! $sent) {
            if ($smtpUser === '' || $smtpPassword === '') {
                $errors[] = 'SMTP: MAIL_USERNAME atau MAIL_PASSWORD belum dikonfigurasi pada server.';
            } else {
                $configuredPort = (int) (config('mail.mailers.smtp.port') ?: 587);
                $ports = array_values(array_unique([$configuredPort, 587, 465]));

                foreach ($ports as $port) {
                    if ($sent) {
                        break;
                    }
                    try {
                        $isSsl = ($port === 465);
                        $transport = new EsmtpTransport($smtpHost, $port, $isSsl);
                        $transport->setUsername($smtpUser);
                        $transport->setPassword($smtpPassword);
                        $mailer = new Mailer($transport);

                        $email = (new Email())
                            ->from(new Address($fromAddress ?: $smtpUser, $fromName))
                            ->to($to)
                            ->subject($subject)
                            ->html($html ?: $text);

                        if ($text !== '') {
                            $email->text($text);
                        }

                        $mailer->send($email);
                        $sent = true;
                        Log::info("[EmailController] Email berhasil terkirim ke {$to} via SMTP (port {$port})");
                    } catch (\Throwable $e) {
                        $errors[] = "SMTP port {$port}: " . $e->getMessage();
                        Log::warning("[EmailController] SMTP port {$port} gagal: " . $e->getMessage());
                    }
                }
            }
        }

        if ($sent) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Email notifikasi berhasil dikirimkan ke ' . $to,
                ],
            ];
        }

        return [
            'status' => 500,
            'body' => [
                'success' => false,
                'message' => 'Gagal mengirim email notifikasi.',
                'errors' => $errors,
            ],
        ];
    }
}
