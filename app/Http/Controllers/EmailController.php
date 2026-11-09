<?php

namespace App\Http\Controllers;

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
     * Dispatcher email terpadu untuk platform in-Time
     * Menggunakan Symfony EsmtpTransport langsung (Gmail SMTP) & Fallback Resend API
     */
    public function sendEmail(Request $request)
    {
        $to = $request->input('to');
        $subject = $request->input('subject');
        $html = $request->input('html', '');
        $text = $request->input('text', '');

        if (empty($to) || empty($subject)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat email tujuan (to) dan subjek (subject) wajib diisi.',
            ], 400);
        }

        $sent = false;
        $errors = [];
        $smtpUser = env('MAIL_USERNAME', env('SMTP_USER', 'naswa.wijaya31@gmail.com'));
        $rawFromName = env('MAIL_FROM_NAME', env('SMTP_FROM_NAME', env('APP_NAME', 'in-Time')));
        $fromName = (empty($rawFromName) || strtolower(trim($rawFromName)) === 'laravel') ? 'in-Time' : trim($rawFromName);
        $fromAddress = env('MAIL_FROM_ADDRESS', $smtpUser);

        $passwords = array_values(array_unique(array_filter([
            env('MAIL_PASSWORD'),
            env('SMTP_PASS'),
        ])));

        $ports = [587, 465];

        // 1. OPSI UTAMA: RESEND REST API (in-Time <onboarding@resend.dev>)
        $resendKey = env('RESEND_API_KEY');
        if ($resendKey) {
            try {
                $fromEmail = env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$resendKey}",
                    'Content-Type' => 'application/json',
                ])->post('https://api.resend.com/emails', [
                    'from' => "in-Time <{$fromEmail}>",
                    'to' => [$to],
                    'subject' => $subject,
                    'html' => $html ?: $text,
                    'text' => $text ?: strip_tags($html),
                ]);

                if ($response->successful()) {
                    $sent = true;
                    Log::info("[EmailController] Email berhasil terkirim ke {$to} via Resend API (in-Time <{$fromEmail}>)");
                } else {
                    $errors[] = 'Resend API: ' . $response->body();
                    Log::warning("[EmailController] Resend API gagal: " . $response->body());
                }
            } catch (\Throwable $e2) {
                $errors[] = 'Resend Exception: ' . $e2->getMessage();
            }
        }

        // 2. OPSI FALLBACK: SYMFONY ESMTP TRANSPORT (GMAIL SMTP)
        if (! $sent) {
            foreach ($passwords as $pwd) {
                if ($sent) {
                    break;
                }
                foreach ($ports as $port) {
                    if ($sent) {
                        break;
                    }
                    try {
                        $isSsl = ($port === 465);
                        $transport = new EsmtpTransport('smtp.gmail.com', $port, $isSsl);
                        $transport->setUsername($smtpUser);
                        $transport->setPassword($pwd);
                        $mailer = new Mailer($transport);

                        $email = (new Email())
                            ->from(new Address($fromAddress, 'in-Time'))
                            ->to($to)
                            ->subject($subject)
                            ->html($html ?: $text);

                        if (! empty($text)) {
                            $email->text($text);
                        }

                        $mailer->send($email);
                        $sent = true;
                        Log::info("[EmailController] Email berhasil terkirim ke {$to} via Gmail SMTP (port {$port})");
                    } catch (\Throwable $e) {
                        $errors[] = "SMTP port {$port}: " . $e->getMessage();
                        Log::warning("[EmailController] SMTP port {$port} gagal: " . $e->getMessage());
                    }
                }
            }
        }

        if ($sent) {
            return response()->json([
                'success' => true,
                'message' => 'Email notifikasi berhasil dikirimkan ke ' . $to,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim email notifikasi.',
            'errors' => $errors,
        ], 500);
    }

    /**
     * Endpoint diagnostik pengujian email
     */
    public function testEmail(Request $request)
    {
        $to = $request->query('to', 'nasywadavina384@gmail.com');
        $req = new Request([
            'to' => $to,
            'subject' => '[Uji Coba] Diagnostik Email in-Time ' . date('d-m-Y H:i:s'),
            'html' => '<div style="font-family:sans-serif;padding:24px;border:1px solid #e2e8f0;border-radius:14px;max-width:550px;background:#ffffff;">'
                . '<div style="background:#1E3A8A;padding:16px 20px;border-radius:10px;margin-bottom:20px;">'
                . '<h2 style="color:#ffffff;margin:0;font-size:18px;">in-Time Sistem Notifikasi</h2>'
                . '</div>'
                . '<p style="color:#0f172a;font-size:14px;line-height:1.6;">Selamat! Sistem pengiriman email otomatis in-Time telah aktif dan berhasil terkirim langsung ke Gmail inbox Anda.</p>'
                . '<p style="color:#475569;font-size:13px;">Waktu pengiriman: <strong>' . date('d-m-Y H:i:s') . ' WIB</strong></p>'
                . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0;" />'
                . '<p style="color:#94a3b8;font-size:11px;">Pemberitahuan resmi otomatis dari platform in-Time.</p>'
                . '</div>',
            'text' => 'Selamat! Sistem pengiriman email otomatis in-Time telah aktif dan berhasil terkirim.',
        ]);

        return $this->sendEmail($req);
    }
}
