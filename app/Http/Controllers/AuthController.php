<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private function findTargetUser(string $id, ?string $fallbackEmail = null): ?User
    {
        $byId = User::where('id', $id)->first();
        if ($byId) {
            return $byId;
        }

        $byPathEmail = User::whereRaw('LOWER(email) = ?', [strtolower(trim($id))])->first();
        if ($byPathEmail) {
            return $byPathEmail;
        }

        if ($fallbackEmail !== null && trim($fallbackEmail) !== '') {
            return User::whereRaw('LOWER(email) = ?', [strtolower(trim($fallbackEmail))])->first();
        }

        return null;
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'nama' => 'nullable|string|max:255',
            'email' => 'required|email:rfc|max:255',
            'password' => 'required|string|min:6|max:255',
            'employment_type' => 'nullable|string|in:PERMANENT,CONTRACT,INTERN,FREELANCE',
            'tipe_kepegawaian' => 'nullable|string|in:PERMANENT,CONTRACT,INTERN,FREELANCE',
            'department' => 'nullable|string|max:100',
            'divisi' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'jabatan' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'nomor_telepon' => 'nullable|string|max:50',
        ]);

        $nama = trim((string) ($validated['nama'] ?? $validated['name'] ?? 'Karyawan Baru'));
        $tipe = $validated['tipe_kepegawaian'] ?? $validated['employment_type'] ?? 'CONTRACT';
        $divisi = trim((string) ($validated['divisi'] ?? $validated['department'] ?? 'Operasional'));
        $jabatan = trim((string) ($validated['jabatan'] ?? $validated['position'] ?? 'Staff'));
        $telepon = $validated['nomor_telepon'] ?? $validated['phone'] ?? null;
        $cleanEmail = strtolower(trim($validated['email']));

        $existing = User::whereRaw('LOWER(email) = ?', [$cleanEmail])->first();
        if ($existing) {
            if ($existing->id === 'usr_super_admin' || in_array(strtoupper((string) $existing->peran), ['SUPER_ADMIN', 'ADMIN'], true)) {
                return response()->json([
                    'message' => 'Alamat email ini milik Administrator dan tidak dapat didaftarkan sebagai akun baru.',
                ], 422);
            }
            if (strtoupper((string) $existing->status) === 'ACTIVE') {
                return response()->json([
                    'message' => 'Alamat email sudah terdaftar dan akun sudah aktif. Silakan masuk via halaman login.',
                ], 422);
            }
            if (strtoupper((string) $existing->status) === 'PENDING_APPROVAL') {
                return response()->json([
                    'message' => 'Alamat email ini sudah terdaftar dan sedang dalam antrean persetujuan Administrator.',
                ], 422);
            }
            return response()->json([
                'message' => 'Alamat email ini sudah pernah terdaftar dan tidak aktif. Silakan hubungi Administrator.',
            ], 422);
        }

        $userId = 'usr_' . time() . '_' . Str::random(6);

        // Akun pendaftar baru selalu dipaksa menjadi EMPLOYEE dengan status PENDING_APPROVAL
        $user = User::create([
            'id' => $userId,
            'nama' => $nama,
            'email' => $cleanEmail,
            'password' => Hash::make($validated['password']),
            'peran' => 'EMPLOYEE',
            'status' => 'PENDING_APPROVAL',
            'tipe_kepegawaian' => $tipe,
            'divisi' => $divisi,
            'jabatan' => $jabatan,
            'foto_profil' => 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($nama),
            'nomor_telepon' => $telepon,
            'tanggal_bergabung' => date('Y-m-d'),
            'sisa_cuti' => 12,
            'waktu_daftar' => date('Y-m-d H:i'),
            'profil_lengkap' => false,
        ]);

        // Token terbatas (scope pending) hanya untuk melengkapi biodata dan mengecek status persetujuan
        $token = $user->createToken('pending-token', ['pending'])->plainTextToken;

        return response()->json([
            'message' => 'Pendaftaran berhasil. Silakan lengkapi biodata diri Anda.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email/username atau password tidak sesuai.',
            ], 401);
        }

        $status = strtoupper((string) $user->status);

        if ($status === 'REJECTED' || $status === 'INACTIVE') {
            return response()->json([
                'message' => 'Pendaftaran atau akses akun Anda dinonaktifkan: ' . ($user->alasan_penolakan ?: 'Tidak memenuhi persyaratan.'),
                'status' => $status,
            ], 403);
        }

        if ($status === 'PENDING_APPROVAL') {
            // Password valid -> berikan token scope terbatas ['pending'] agar PendingApprovalScreen
            // dapat memantau persetujuan secara terautentikasi tanpa membuka endpoint publik
            $pendingToken = $user->createToken('pending-token', ['pending'])->plainTextToken;

            return response()->json([
                'message' => 'Akun Anda sedang dalam antrean peninjauan oleh Administrator. Harap tunggu hingga akun diverifikasi.',
                'status' => 'PENDING_APPROVAL',
                'token' => $pendingToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->nama,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'role' => $user->peran,
                    'peran' => $user->peran,
                    'status' => $user->status,
                    'department' => $user->divisi,
                    'position' => $user->jabatan,
                    'employment_type' => $user->tipe_kepegawaian,
                    'avatar' => $user->foto_profil,
                    'profil_lengkap' => (bool) $user->profil_lengkap,
                    'is_profile_complete' => (bool) $user->profil_lengkap,
                ],
            ], 403);
        }

        $token = $user->createToken('auth-token', ['*'])->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $authUser = $request->user();
        if (! $authUser) {
            return response()->json([
                'message' => 'Sesi login tidak valid. Silakan login kembali.',
            ], 401);
        }

        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        $isAdmin = in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true) && strtoupper((string) $authUser->status) === 'ACTIVE';

        $targetId = $request->input('id', $request->input('user_id'));
        $targetEmail = $request->input('current_email');

        $user = $authUser;
        if (($targetId && $targetId !== $authUser->id) || ($targetEmail && strtolower(trim((string) $targetEmail)) !== strtolower((string) $authUser->email))) {
            if (! $isAdmin) {
                return response()->json([
                    'message' => 'Operasi ditolak: Anda tidak memiliki izin untuk mengubah profil pengguna lain.',
                ], 403);
            }

            $candidate = $targetId
                ? User::where('id', $targetId)->first()
                : User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $targetEmail))])->first();

            if (! $candidate) {
                return response()->json(['message' => 'Pengguna target tidak ditemukan.'], 404);
            }

            // Admin biasa tidak boleh memodifikasi akun Super Admin
            if ($authRole !== 'SUPER_ADMIN' && ($candidate->id === 'usr_super_admin' || strtoupper((string) $candidate->peran) === 'SUPER_ADMIN')) {
                return response()->json([
                    'message' => 'Operasi ditolak: Akun Super Administrator tidak dapat dimodifikasi oleh Admin.',
                ], 403);
            }

            $user = $candidate;
        }

        // Proteksi agar data pendaftar baru tidak menimpa akun Super Admin
        if ($user->id === 'usr_super_admin' && $targetId && $targetId !== 'usr_super_admin') {
            return response()->json([
                'message' => 'Operasi ditolak: Akun Super Administrator tidak dapat ditimpa.',
            ], 403);
        }

        $nama = $request->input('nama', $request->input('name', $user->nama));
        $email = $request->input('email', $user->email);
        $foto = $request->input('foto_profil', $request->input('avatar', $user->foto_profil));
        if (is_string($foto) && ! InputSanitizer::isSafeMediaUrl($foto)) {
            return response()->json([
                'message' => 'Format foto profil tidak valid atau menggunakan skema URL yang dilarang.',
            ], 422);
        }
        $telepon = $request->input('nomor_telepon', $request->input('phone', $user->nomor_telepon));
        $divisi = $request->input('divisi', $request->input('department', $user->divisi));
        $jabatan = $request->input('jabatan', $request->input('position', $user->jabatan));
        $bank = $request->input('nama_bank', $request->input('bank_name', $user->nama_bank));
        $norek = $request->input('nomor_rekening', $request->input('bank_account_number', $user->nomor_rekening));
        $an = $request->input('nama_pemilik_rekening', $request->input('bank_account_holder', $user->nama_pemilik_rekening));
        $daruratNama = $request->input('nama_kontak_darurat', $request->input('emergency_contact_name', $user->nama_kontak_darurat));
        $daruratHp = $request->input('nomor_kontak_darurat', $request->input('emergency_contact_phone', $user->nomor_kontak_darurat));
        $alamat = $request->input('alamat', $request->input('address', $user->alamat));
        $lengkap = $request->input('profil_lengkap', $request->input('is_profile_complete', $user->profil_lengkap));
        $gender = $request->input('jenis_kelamin', $request->input('gender', $user->jenis_kelamin));
        $birthDate = $request->input('tanggal_lahir', $request->input('birth_date', $request->input('birthDate', $user->tanggal_lahir)));

        // Catatan: Kolom kritis (peran, status, sisa_cuti) sengaja TIDAK pernah diperbarui melalui endpoint profil
        $data = [
            'nama' => $nama,
            'foto_profil' => $foto,
            'nomor_telepon' => $telepon,
            'jenis_kelamin' => $gender ?: ($user->jenis_kelamin ?: 'L'),
            'tanggal_lahir' => $birthDate ?: ($user->tanggal_lahir ?: '2000-01-01'),
            'divisi' => $divisi,
            'jabatan' => $jabatan,
            'nama_bank' => $bank,
            'nomor_rekening' => $norek,
            'nama_pemilik_rekening' => $an,
            'nama_kontak_darurat' => $daruratNama,
            'nomor_kontak_darurat' => $daruratHp,
            'alamat' => $alamat,
            'profil_lengkap' => (bool) $lengkap,
        ];

        // Validasi dan update email jika diubah
        if (! empty($email) && trim(strtolower((string) $email)) !== trim(strtolower((string) $user->email))) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['message' => 'Format alamat email tidak valid.'], 422);
            }
            $emailExists = User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])
                ->where('id', '!=', $user->id)
                ->exists();
            if ($emailExists) {
                return response()->json([
                    'message' => 'Alamat email baru sudah digunakan oleh akun lain.',
                ], 422);
            }
            $data['email'] = strtolower(trim((string) $email));
        }

        // Tipe kepegawaian hanya boleh diubah oleh Admin/SuperAdmin atau saat pengguna baru mengisi onboarding
        if (($isAdmin || strtoupper((string) $user->status) === 'PENDING_APPROVAL')
            && ($request->has('employment_type') || $request->has('tipe_kepegawaian'))
        ) {
            $reqType = strtoupper((string) $request->input('employment_type', $request->input('tipe_kepegawaian')));
            if (in_array($reqType, ['PERMANENT', 'CONTRACT', 'INTERN', 'FREELANCE'], true)) {
                $data['tipe_kepegawaian'] = $reqType;
            }
        }

        // Update kata sandi baru jika dikirim
        $passwordChanged = false;
        if ($request->has('password') && ! empty($request->password)) {
            $rawPass = (string) $request->password;
            if (strlen($rawPass) < 6) {
                return response()->json(['message' => 'Password minimal terdiri dari 6 karakter.'], 422);
            }
            $data['password'] = Hash::make($rawPass);
            $passwordChanged = true;
        }

        $user->update($data);
        if ($passwordChanged) {
            // Cabut seluruh token lama agar sesi yang berpotensi bocor langsung hangus saat password diganti
            $user->tokens()->delete();
        }
        $freshUser = $user->fresh();

        $responsePayload = [
            'message' => 'Biodata diri dan kredensial berhasil diperbarui.',
            'user' => $freshUser,
        ];

        // Hanya kembalikan token baru jika pengguna memperbarui profilnya sendiri
        if ($freshUser->id === $authUser->id) {
            $ability = strtoupper((string) $freshUser->status) === 'ACTIVE' ? ['*'] : ['pending'];
            $responsePayload['token'] = $freshUser->createToken('auth-token', $ability)->plainTextToken;
        }

        return response()->json($responsePayload);
    }

    public function getUsers(Request $request)
    {
        $authUser = $request->user();
        $role = strtoupper((string) ($authUser->peran ?? ''));
        $isAdmin = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);

        $query = User::query();
        if ($isAdmin) {
            $query->where('status', '!=', 'REJECTED');
        } else {
            // Non-admin hanya boleh melihat rekan aktif (tidak mengekspos pendaftar PENDING_APPROVAL)
            $query->where('status', 'ACTIVE');
        }
        $users = $query->orderBy('nama')->get();

        // Jika pemanggil adalah karyawan biasa, sembunyikan data finansial, kontak darurat, tgl lahir & kuota cuti milik karyawan lain
        if (! $isAdmin) {
            $sensitiveFields = [
                'nama_bank',
                'nomor_rekening',
                'nama_pemilik_rekening',
                'nama_kontak_darurat',
                'nomor_kontak_darurat',
                'alamat',
                'alasan_penolakan',
                'tanggal_lahir',
                'sisa_cuti',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'emergency_contact_name',
                'emergency_contact_phone',
                'address',
                'birth_date',
                'birthDate',
                'leave_balance',
            ];
            $users->each(function (User $u) use ($authUser, $sensitiveFields) {
                if ($u->id !== $authUser->id) {
                    $u->makeHidden($sensitiveFields);
                }
            });
        }

        return response()->json($users);
    }

    public function checkUserStatus(Request $request)
    {
        $authUser = $request->user();
        if (! $authUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        $isAdmin = in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true) && strtoupper((string) $authUser->status) === 'ACTIVE';

        // Non-admin hanya boleh mengecek status akunnya sendiri (mencegah enumerasi user & kebocoran PII)
        $user = $authUser;
        if ($isAdmin) {
            $id = $request->query('id');
            $email = $request->query('email');
            if ($id) {
                $user = User::where('id', $id)->first();
            } elseif ($email) {
                $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))])->first();
            }
        }

        if (! $user) {
            return response()->json(['message' => 'Pengguna tidak ditemukan.'], 404);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->nama,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->peran,
            'profil_lengkap' => (bool) $user->profil_lengkap,
        ]);
    }

    public function approveUser(Request $request, $id)
    {
        $authUser = $request->user();
        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        if (! $authUser || ! in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang dapat menyetujui akun.',
            ], 403);
        }

        $email = $request->input('email', $request->json('email'));
        $user = $this->findTargetUser((string) $id, is_string($email) ? $email : null);
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $targetRole = strtoupper((string) $request->input('role', $request->input('peran', $request->json('role', $request->json('peran', 'EMPLOYEE')))));
        if (! in_array($targetRole, ['SUPER_ADMIN', 'ADMIN', 'EMPLOYEE'], true)) {
            $targetRole = 'EMPLOYEE';
        }

        // Admin biasa hanya boleh menyetujui pendaftar menjadi EMPLOYEE; Admin/SuperAdmin hanya oleh Super Admin
        if ($authRole !== 'SUPER_ADMIN' && ($targetRole !== 'EMPLOYEE' || in_array(strtoupper((string) $user->peran), ['SUPER_ADMIN', 'ADMIN'], true) || $user->id === 'usr_super_admin')) {
            return response()->json([
                'message' => 'Hanya Super Admin yang berhak menetapkan peran Admin atau Super Admin.',
            ], 403);
        }

        $user->update([
            'status' => 'ACTIVE',
            'profil_lengkap' => true,
            'peran' => $targetRole,
        ]);

        // Hapus token pending lama jika ada agar sesi baru menggunakan izin aktif penuh
        $user->tokens()->where('name', 'pending-token')->delete();

        $appUrl = rtrim((string) config('app.url', ''), '/');
        $escapedAppUrl = e($appUrl);
        $portalCtaHtml = $appUrl !== ''
            ? "<div style='text-align:center;margin:28px 0 16px 0;'><a href='{$escapedAppUrl}' style='background-color:#1E3A8A;color:#ffffff;padding:12px 26px;font-size:13px;font-weight:700;text-decoration:none;border-radius:10px;display:inline-block;'>Buka Portal in-Time &rarr;</a></div>"
            : '';

        try {
            $roleLabel = $user->peran === 'SUPER_ADMIN' ? 'Superadmin' : ($user->peran === 'ADMIN' ? 'Admin' : 'Karyawan');
            $safeNama = e($user->nama);
            $safeEmail = e($user->email);
            $safeRoleLabel = e($roleLabel);
            app(EmailController::class)->dispatchEmail([
                'to' => $user->email,
                'subject' => "Akun Terverifikasi: Selamat Datang di in-Time, {$user->nama}!",
                'html' => "<div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:580px;margin:auto;padding:24px;background:#f8fafc;'>"
                    . "<div style='background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);'>"
                    . "<div style='background:#1E3A8A;padding:20px 28px;color:#ffffff;'>"
                    . "<h2 style='margin:0;font-size:18px;'>Pendaftaran Akun Telah Disetujui</h2>"
                    . "</div>"
                    . "<div style='padding:24px 28px;'>"
                    . "<p style='font-size:14px;color:#1e293b;line-height:1.6;'>Halo <strong>{$safeNama}</strong>,</p>"
                    . "<p style='font-size:14px;color:#334155;line-height:1.6;'>Pendaftaran akun Anda pada platform in-Time telah resmi disetujui. Akun Anda saat ini sudah aktif dan dapat langsung digunakan untuk presensi dan manajemen tugas.</p>"
                    . "<table style='width:100%;border-collapse:collapse;margin:20px 0;'>"
                    . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:10px 0;font-weight:600;color:#475569;width:35%;font-size:13px;'>Nama Pengguna:</td><td style='padding:10px 0;color:#0f172a;font-size:13px;'>{$safeNama}</td></tr>"
                    . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:10px 0;font-weight:600;color:#475569;font-size:13px;'>Email Terdaftar:</td><td style='padding:10px 0;color:#0f172a;font-size:13px;'>{$safeEmail}</td></tr>"
                    . "<tr style='border-bottom:1px solid #f1f5f9;'><td style='padding:10px 0;font-weight:600;color:#475569;font-size:13px;'>Peran Akun:</td><td style='padding:10px 0;color:#0f172a;font-size:13px;'>{$safeRoleLabel}</td></tr>"
                    . "<tr><td style='padding:10px 0;font-weight:600;color:#475569;font-size:13px;'>Status Akun:</td><td style='padding:10px 0;color:#16a34a;font-weight:bold;font-size:13px;'>Aktif (Terverifikasi)</td></tr>"
                    . "</table>"
                    . $portalCtaHtml
                    . "</div>"
                    . "</div>"
                    . "</div>",
                'text' => "Halo {$user->nama},\n\nAkun in-Time Anda telah resmi disetujui dan aktif.\nEmail: {$user->email}\nPeran: {$roleLabel}\nStatus: Aktif" . ($appUrl !== '' ? "\n\nSilakan login ke {$appUrl}" : ''),
            ]);
        } catch (\Throwable $mailErr) {
            Log::warning('[AuthController] Gagal auto-send email persetujuan: ' . $mailErr->getMessage());
        }

        return response()->json([
            'message' => 'Akun karyawan berhasil disetujui.',
            'user' => $user->fresh(),
        ]);
    }

    public function rejectUser(Request $request, $id)
    {
        $authUser = $request->user();
        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        if (! $authUser || ! in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang dapat menolak pendaftaran.',
            ], 403);
        }

        $reason = $request->input('alasan', $request->input('reason'));

        $user = $this->findTargetUser((string) $id);
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->id === 'usr_super_admin' || strtoupper((string) $user->peran) === 'SUPER_ADMIN') {
            return response()->json([
                'message' => 'Akun Super Admin tidak dapat ditolak atau dinonaktifkan.',
            ], 403);
        }

        if ($authRole !== 'SUPER_ADMIN' && strtoupper((string) $user->peran) === 'ADMIN') {
            return response()->json([
                'message' => 'Hanya Super Admin yang dapat menonaktifkan akun Admin.',
            ], 403);
        }

        $user->update([
            'status' => 'REJECTED',
            'alasan_penolakan' => $reason,
        ]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Pendaftaran karyawan telah ditolak.',
            'user' => $user->fresh(),
        ]);
    }

    public function updateUserRole(Request $request, $id)
    {
        $authUser = $request->user();
        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        if (! $authUser || ! in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang berhak mengubah peran.',
            ], 403);
        }

        $email = $request->input('email', $request->json('email'));
        $user = $this->findTargetUser((string) $id, is_string($email) ? $email : null);
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->id === 'usr_super_admin') {
            return response()->json([
                'message' => 'Peran akun Super Admin utama tidak dapat diubah.',
            ], 403);
        }

        $newRole = strtoupper((string) $request->input('role', $request->input('peran', $request->json('role', $request->json('peran')))));
        if (! in_array($newRole, ['SUPER_ADMIN', 'ADMIN', 'EMPLOYEE'], true)) {
            return response()->json(['message' => 'Role tidak valid.'], 422);
        }

        // Hanya Super Admin yang dapat menetapkan/mencabut peran ADMIN atau SUPER_ADMIN
        $currentTargetRole = strtoupper((string) $user->peran);
        if ($authRole !== 'SUPER_ADMIN' && ($newRole !== 'EMPLOYEE' || in_array($currentTargetRole, ['SUPER_ADMIN', 'ADMIN'], true))) {
            return response()->json([
                'message' => 'Hanya Super Admin yang berhak menetapkan atau mengubah peran Admin maupun Super Admin.',
            ], 403);
        }

        $user->update(['peran' => $newRole]);

        return response()->json([
            'message' => "Peran akun {$user->nama} berhasil diubah menjadi {$newRole}.",
            'user' => $user->fresh(),
        ]);
    }

    public function updateEmploymentType(Request $request, $id)
    {
        $authUser = $request->user();
        $authRole = strtoupper((string) ($authUser->peran ?? ''));
        if (! $authUser || ! in_array($authRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin atau Super Admin yang berhak mengubah tipe kepegawaian.',
            ], 403);
        }

        $email = $request->input('email', $request->json('email'));
        $user = $this->findTargetUser((string) $id, is_string($email) ? $email : null);
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($authRole !== 'SUPER_ADMIN' && ($user->id === 'usr_super_admin' || strtoupper((string) $user->peran) === 'SUPER_ADMIN')) {
            return response()->json([
                'message' => 'Tipe kepegawaian akun Super Admin tidak dapat diubah oleh Admin.',
            ], 403);
        }

        $newType = strtoupper((string) $request->input('employment_type', $request->input('tipe_kepegawaian', $request->json('employment_type', $request->json('tipe_kepegawaian', 'CONTRACT')))));
        if (! in_array($newType, ['PERMANENT', 'CONTRACT', 'INTERN', 'FREELANCE'], true)) {
            return response()->json(['message' => 'Tipe kepegawaian tidak valid.'], 422);
        }

        $user->update(['tipe_kepegawaian' => $newType]);

        return response()->json([
            'message' => "Tipe kepegawaian {$user->nama} berhasil diubah menjadi {$newType}.",
            'user' => $user->fresh(),
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $currentUser = $request->user();
        $userRole = $currentUser ? strtoupper((string) $currentUser->peran) : '';
        if (! $currentUser || ! in_array($userRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin/Super Admin yang berhak menghapus karyawan.',
            ], 403);
        }

        $user = $this->findTargetUser((string) $id);
        if (! $user) {
            return response()->json([
                'message' => 'User sudah tidak ada di sistem database.',
                'id' => $id,
            ], 200);
        }

        if ($user->id === $currentUser->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 403);
        }

        if ($user->id === 'usr_super_admin' || strtoupper((string) $user->peran) === 'SUPER_ADMIN') {
            return response()->json([
                'message' => 'Akun Super Admin utama tidak dapat dihapus.',
            ], 403);
        }

        if ($userRole !== 'SUPER_ADMIN' && strtoupper((string) $user->peran) === 'ADMIN') {
            return response()->json([
                'message' => 'Hanya Super Admin yang berhak menghapus akun Admin.',
            ], 403);
        }

        $targetId = $user->id;

        DB::beginTransaction();
        try {
            DB::table('tugas_proyek')
                ->where('ditugaskan_ke', $targetId)
                ->orWhere('dibuat_oleh', $targetId)
                ->delete();

            $userTopicIds = DB::table('topik_diskusi')->where('dibuat_oleh_id', $targetId)->pluck('id');
            if ($userTopicIds->isNotEmpty()) {
                DB::table('pesan_diskusi')->whereIn('topik_id', $userTopicIds)->delete();
                DB::table('topik_diskusi')->whereIn('id', $userTopicIds)->delete();
            }

            DB::table('pesan_diskusi')->where('pengguna_id', $targetId)->delete();
            DB::table('logbook_harian')->where('pengguna_id', $targetId)->delete();
            DB::table('pengajuan_izin')->where('pengguna_id', $targetId)->delete();
            DB::table('presensi')->where('pengguna_id', $targetId)->delete();

            DB::table('personal_access_tokens')
                ->where('tokenable_id', $targetId)
                ->delete();

            DB::table('pengguna')->where('id', $targetId)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Karyawan berhasil dihapus dari sistem dan database.',
                'id' => $targetId,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[AuthController::deleteUser] ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menghapus karyawan.',
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }
}
