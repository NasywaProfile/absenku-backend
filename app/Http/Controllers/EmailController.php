<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    /**
     * Dispatcher email terpadu untuk platform in-Time
     * Menggunakan konfigurasi dinamis dari .env (SMTP Gmail & Resend API)
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

        // 1. OPSI UTAMA: Kirim melalui Laravel Mail default (SMTP)
        try {
            Mail::html($html ?: $text, function ($message) use ($to, $subject, $text) {
                $message->to($to)
                    ->subject($subject);
                if (! empty($text)) {
                    $message->text($text);
                }
            });
            $sent = true;
            Log::info("[EmailController] Berhasil kirim email via SMTP ke: {$to} | Subjek: {$subject}");
        } catch (\Throwable $e) {
            $errors[] = 'SMTP: ' . $e->getMessage();
            Log::warning("[EmailController] SMTP gagal kirim ke {$to}: " . $e->getMessage());
        }

        // 2. FALLBACK: Resend REST API jika diatur di .env
        if (! $sent) {
            $resendKey = env('RESEND_API_KEY');
            if ($resendKey) {
                try {
                    $fromEmail = env('RESEND_FROM_EMAIL', 'onboarding@resend.dev');
                    $fromName = env('MAIL_FROM_NAME', 'in-Time Notifikasi');

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
                        Log::info("[EmailController] Berhasil kirim email via Resend API ke: {$to}");
                    } else {
                        $errors[] = 'Resend API: ' . $response->body();
                        Log::warning("[EmailController] Resend API gagal: " . $response->body());
                    }
                } catch (\Throwable $e2) {
                    $errors[] = 'Resend Exception: ' . $e2->getMessage();
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
}
