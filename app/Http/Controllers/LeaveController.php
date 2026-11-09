<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeaveRequest::query();

        $role = strtoupper($user->peran ?? '');
        if (! in_array($role, ['SUPER_ADMIN', 'ADMIN'])) {
            $query->where('pengguna_id', $user->id);
        }

        if ($request->has('status') || $request->has('status_pengajuan')) {
            $targetStatus = $request->input('status_pengajuan', $request->input('status'));
            $query->where('status_pengajuan', $targetStatus);
        }

        $leaves = $query->orderBy('created_at', 'desc')->get();

        return response()->json($leaves);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $kategori = $request->input('kategori_izin', $request->input('category'));
        $tglMulai = $request->input('tanggal_mulai', $request->input('start_date'));
        $tglSelesai = $request->input('tanggal_selesai', $request->input('end_date'));
        $jmlHari = $request->input('jumlah_hari', $request->input('total_days', 1));
        $alasan = $request->input('alasan', $request->input('reason'));
        $namaLampiran = $request->input('nama_lampiran', $request->input('attachment_name'));
        $dataLampiran = $request->input('data_lampiran', $request->input('attachment_data'));
        $tipeLampiran = $request->input('tipe_lampiran', $request->input('attachment_type'));

        // Cek dan cegah duplikasi perizinan yang sama persis
        $existing = LeaveRequest::where('pengguna_id', $user->id)
            ->where('tanggal_mulai', $tglMulai)
            ->where('tanggal_selesai', $tglSelesai)
            ->where('kategori_izin', $kategori)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Pengajuan sudah terdaftar.',
                'leave' => $existing,
            ], 200);
        }

        $leaveId = $request->input('id', 'lve_' . time() . '_' . Str::random(4));

        $leave = LeaveRequest::create([
            'id' => $leaveId,
            'pengguna_id' => $user->id,
            'nama_karyawan' => $user->nama,
            'foto_karyawan' => $user->foto_profil,
            'divisi' => $user->divisi,
            'kategori_izin' => $kategori,
            'tanggal_mulai' => $tglMulai,
            'tanggal_selesai' => $tglSelesai,
            'jumlah_hari' => $jmlHari,
            'alasan' => $alasan,
            'nama_lampiran' => $namaLampiran,
            'data_lampiran' => $dataLampiran,
            'tipe_lampiran' => $tipeLampiran,
            'status_pengajuan' => 'PENDING',
            'waktu_pengajuan' => date('Y-m-d H:i'),
        ]);

        // Kirim Notifikasi Email Otomatis ke Super Admin / Admin via Gmail SMTP
        try {
            $kategoriLabels = [
                'LEAVE' => 'Izin / Cuti Tahunan',
                'CUTI' => 'Cuti Tahunan',
                'SICK' => 'Izin Sakit',
                'WFH' => 'Work From Home (WFH)',
                'BUSINESS_TRIP' => 'Dinas Luar Kota',
            ];
            $labelKategori = $kategoriLabels[$kategori] ?? $kategori;

            $recipientEmail = config('mail.from.address', 'naswa.wijaya31@gmail.com');

            $htmlContent = "
            <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px;'>
                <div style='background-color: #0284c7; color: white; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;'>
                    <h2 style='margin: 0; font-size: 18px;'>Pemberitahuan Pengajuan Izin Baru</h2>
                </div>
                <p>Halo <strong>Super Admin</strong>,</p>
                <p>Karyawan berikut telah mengajukan permohonan izin/cuti melalui sistem <strong>Absenku</strong>:</p>
                
                <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold; width: 35%;'>Nama Karyawan:</td>
                        <td style='padding: 8px 0;'>{$user->nama}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Divisi:</td>
                        <td style='padding: 8px 0;'>{$user->divisi}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Jenis Pengajuan:</td>
                        <td style='padding: 8px 0;'><span style='background-color: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px; font-weight: bold;'>{$labelKategori}</span></td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Periode:</td>
                        <td style='padding: 8px 0;'>{$tglMulai} s.d. {$tglSelesai} ({$jmlHari} hari)</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Alasan:</td>
                        <td style='padding: 8px 0;'>{$alasan}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid #e2e8f0;'>
                        <td style='padding: 8px 0; font-weight: bold;'>Waktu Pengajuan:</td>
                        <td style='padding: 8px 0;'>{$leave->waktu_pengajuan} WIB</td>
                    </tr>
                </table>

                <p style='margin-top: 20px;'>Silakan buka aplikasi Absenku untuk meninjau dan memberikan persetujuan:</p>
                <div style='margin-top: 15px;'>
                    <a href='http://localhost:3000' style='background-color: #0284c7; color: white; padding: 10px 18px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Buka Panel Absenku</a>
                </div>
                
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0 15px 0;' />
                <p style='font-size: 12px; color: #64748b; margin: 0;'>Pesan ini dikirimkan otomatis oleh sistem Absenku.</p>
            </div>";

            $emailReq = new Request([
                'to' => $recipientEmail,
                'subject' => "[in-Time] Pengajuan {$labelKategori} Baru - {$user->nama}",
                'html' => $htmlContent,
                'text' => "Pemberitahuan Pengajuan Izin Baru: {$user->nama} ({$labelKategori}) dari {$tglMulai} s.d. {$tglSelesai}.",
            ]);
            app(EmailController::class)->sendEmail($emailReq);
        } catch (\Throwable $mailErr) {
            \Illuminate\Support\Facades\Log::warning('Gagal mengirimkan notifikasi email pengajuan izin: ' . $mailErr->getMessage());
        }

        return response()->json([
            'message' => 'Pengajuan izin/cuti berhasil dikirim.',
            'leave' => $leave,
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $admin = $request->user();
        $leave = LeaveRequest::findOrFail($id);

        $leave->update([
            'status_pengajuan' => 'APPROVED',
            'disetujui_oleh' => $admin->nama,
            'waktu_persetujuan' => date('Y-m-d H:i'),
        ]);

        // Potong sisa cuti jika kategori adalah LEAVE / CUTI
        $applicant = User::find($leave->pengguna_id);
        if (in_array($leave->kategori_izin, ['LEAVE', 'CUTI'])) {
            if ($applicant && $applicant->sisa_cuti >= $leave->jumlah_hari) {
                $applicant->decrement('sisa_cuti', $leave->jumlah_hari);
            }
        }

        // Kirim email konfirmasi ACC ke karyawan pemohon
        if ($applicant && ! empty($applicant->email)) {
            try {
                $emailReq = new Request([
                    'to' => $applicant->email,
                    'subject' => "[in-Time] Pengajuan Izin Disetujui ({$leave->kategori_izin})",
                    'html' => "<div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:580px;margin:auto;padding:24px;background:#f8fafc;'>"
                        . "<div style='background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;'>"
                        . "<div style='background:#1E3A8A;padding:20px 28px;color:#ffffff;'>"
                        . "<h2 style='margin:0;font-size:18px;'>Pengajuan Izin Telah Disetujui</h2>"
                        . "</div>"
                        . "<div style='padding:24px 28px;'>"
                        . "<p style='font-size:14px;color:#1e293b;'>Halo <strong>{$applicant->nama}</strong>,</p>"
                        . "<p style='font-size:14px;color:#334155;'>Kabar baik! Pengajuan izin/cuti Anda telah resmi disetujui oleh Administrator.</p>"
                        . "<table style='width:100%;border-collapse:collapse;margin:20px 0;'>"
                        . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;font-weight:600;color:#475569;'>Kategori:</td><td style='padding:8px 0;color:#0f172a;'>{$leave->kategori_izin}</td></tr>"
                        . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;font-weight:600;color:#475569;'>Periode:</td><td style='padding:8px 0;color:#0f172a;'>{$leave->tanggal_mulai} s/d {$leave->tanggal_selesai} ({$leave->jumlah_hari} Hari)</td></tr>"
                        . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;font-weight:600;color:#475569;'>Disetujui Oleh:</td><td style='padding:8px 0;color:#0f172a;'>{$admin->nama}</td></tr>"
                        . "<tr><td style='padding:8px 0;font-weight:600;color:#475569;'>Status:</td><td style='padding:8px 0;color:#16a34a;font-weight:bold;'>DISETUJUI (APPROVED)</td></tr>"
                        . "</table>"
                        . "</div>"
                        . "</div>"
                        . "</div>",
                    'text' => "Halo {$applicant->nama},\n\nPengajuan {$leave->kategori_izin} Anda ({$leave->tanggal_mulai} s/d {$leave->tanggal_selesai}) telah DISETUJUI oleh {$admin->nama}.",
                ]);
                app(EmailController::class)->sendEmail($emailReq);
            } catch (\Throwable $e) {
                \Log::warning("Gagal kirim email ACC izin: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Pengajuan berhasil disetujui.',
            'leave' => $leave,
        ]);
    }

    public function reject(Request $request, $id)
    {
        $admin = $request->user();
        $leave = LeaveRequest::findOrFail($id);

        $reason = $request->input('alasan', $request->input('reason'));

        $leave->update([
            'status_pengajuan' => 'REJECTED',
            'disetujui_oleh' => $admin->nama,
            'waktu_persetujuan' => date('Y-m-d H:i'),
            'alasan_penolakan' => $reason,
        ]);

        // Kirim email notifikasi penolakan ke karyawan pemohon
        $applicant = User::find($leave->pengguna_id);
        if ($applicant && ! empty($applicant->email)) {
            try {
                $emailReq = new Request([
                    'to' => $applicant->email,
                    'subject' => "[in-Time] Pengajuan Izin Ditolak ({$leave->kategori_izin})",
                    'html' => "<div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:580px;margin:auto;padding:24px;background:#f8fafc;'>"
                        . "<div style='background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;'>"
                        . "<div style='background:#dc2626;padding:20px 28px;color:#ffffff;'>"
                        . "<h2 style='margin:0;font-size:18px;'>Pemberitahuan Penolakan Izin</h2>"
                        . "</div>"
                        . "<div style='padding:24px 28px;'>"
                        . "<p style='font-size:14px;color:#1e293b;'>Halo <strong>{$applicant->nama}</strong>,</p>"
                        . "<p style='font-size:14px;color:#334155;'>Mohon maaf, pengajuan izin/cuti Anda belum dapat disetujui oleh Administrator.</p>"
                        . "<table style='width:100%;border-collapse:collapse;margin:20px 0;'>"
                        . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;font-weight:600;color:#475569;'>Kategori:</td><td style='padding:8px 0;color:#0f172a;'>{$leave->kategori_izin}</td></tr>"
                        . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:8px 0;font-weight:600;color:#475569;'>Alasan Penolakan:</td><td style='padding:8px 0;color:#dc2626;font-weight:bold;'>" . ($reason ?: 'Tidak memenuhi ketentuan operasional') . "</td></tr>"
                        . "</table>"
                        . "</div>"
                        . "</div>"
                        . "</div>",
                    'text' => "Halo {$applicant->nama},\n\nPengajuan {$leave->kategori_izin} Anda ditolak. Alasan: " . ($reason ?: '-'),
                ]);
                app(EmailController::class)->sendEmail($emailReq);
            } catch (\Throwable $e) {
                \Log::warning("Gagal kirim email penolakan izin: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Pengajuan telah ditolak.',
            'leave' => $leave,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $leave = LeaveRequest::where('id', $id)->first();
        if (! $leave) {
            return response()->json(['message' => 'Pengajuan berhasil dihapus.'], 200);
        }

        $user = $request->user();
        if ($user && ! in_array($user->peran, ['SUPER_ADMIN', 'ADMIN']) && $leave->pengguna_id !== $user->id) {
            return response()->json(['message' => 'Tidak memiliki izin.'], 403);
        }

        $leave->delete();

        return response()->json([
            'message' => 'Pengajuan berhasil dihapus.',
        ]);
    }
}
