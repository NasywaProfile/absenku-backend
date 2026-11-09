<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'nama' => 'nullable|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'employment_type' => 'nullable|string',
            'tipe_kepegawaian' => 'nullable|string',
            'department' => 'nullable|string',
            'divisi' => 'nullable|string',
            'position' => 'nullable|string',
            'jabatan' => 'nullable|string',
            'phone' => 'nullable|string',
            'nomor_telepon' => 'nullable|string',
        ]);

        $nama = $validated['nama'] ?? $validated['name'] ?? 'Karyawan Baru';
        $tipe = $validated['tipe_kepegawaian'] ?? $validated['employment_type'] ?? 'CONTRACT';
        $divisi = $validated['divisi'] ?? $validated['department'] ?? 'Operasional';
        $jabatan = $validated['jabatan'] ?? $validated['position'] ?? 'Staff';
        $telepon = $validated['nomor_telepon'] ?? $validated['phone'] ?? null;

        $existing = User::where('email', $validated['email'])->first();
        $userId = $existing ? $existing->id : ('usr_' . time() . '_' . Str::random(4));

        $user = User::updateOrCreate(
            ['email' => $validated['email']],
            [
                'id' => $userId,
                'nama' => $nama,
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
            ]
        );

        $token = $user->createToken('auth-token')->plainTextToken;

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

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email/username atau password tidak sesuai.',
            ], 401);
        }

        if ($user->status === 'REJECTED') {
            return response()->json([
                'message' => 'Pendaftaran akun Anda ditolak: ' . ($user->alasan_penolakan ?: 'Tidak memenuhi persyaratan.'),
                'status' => 'REJECTED',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

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
        $user = $request->user();

        // Fallback jika token authorization sedang terputus namun user mengirimkan identitas id/email
        if (! $user) {
            $id = $request->input('id', $request->input('user_id'));
            $email = $request->input('current_email', $request->input('email'));
            if ($id) {
                $user = User::find($id);
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            }
        }

        if (! $user) {
            return response()->json([
                'message' => 'Sesi login tidak valid. Silakan login kembali.',
            ], 401);
        }

        $nama = $request->input('nama', $request->input('name', $user->nama));
        $email = $request->input('email', $user->email);
        $foto = $request->input('foto_profil', $request->input('avatar', $user->foto_profil));
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

        $data = [
            'nama' => $nama,
            'foto_profil' => $foto,
            'nomor_telepon' => $telepon,
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
        if (! empty($email) && $email !== $user->email) {
            $emailExists = User::where('email', $email)->where('id', '!=', $user->id)->exists();
            if ($emailExists) {
                return response()->json([
                    'message' => 'Alamat email baru sudah digunakan oleh akun lain.',
                ], 422);
            }
            $data['email'] = $email;
        }

        // Update tipe kepegawaian jika ada perubahan
        if ($request->has('employment_type') || $request->has('tipe_kepegawaian')) {
            $data['tipe_kepegawaian'] = $request->input('employment_type', $request->input('tipe_kepegawaian'));
        }

        // Update kata sandi baru jika dikirim
        if ($request->has('password') && ! empty($request->password)) {
            $rawPass = $request->password;
            $data['password'] = str_starts_with($rawPass, '$2y$') ? $rawPass : Hash::make($rawPass);
        }

        $user->update($data);
        $freshUser = $user->fresh();
        $token = $freshUser->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Biodata diri dan kredensial berhasil diperbarui.',
            'user' => $freshUser,
            'token' => $token,
        ]);
    }

    public function getUsers(Request $request)
    {
        $users = User::where('status', '!=', 'REJECTED')->orderBy('nama')->get();
        return response()->json($users);
    }

    public function checkUserStatus(Request $request)
    {
        $id = $request->query('id');
        $email = $request->query('email');

        $user = null;
        if ($id) {
            $user = User::where('id', $id)->first();
        }
        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }
        if (! $user && $request->user()) {
            $user = $request->user();
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
            'user' => $user,
        ]);
    }

    public function approveUser(Request $request, $id)
    {
        $user = User::where('id', $id)->orWhere('email', $id)->first();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        $user->update([
            'status' => 'ACTIVE',
            'profil_lengkap' => true,
        ]);

        return response()->json([
            'message' => 'Akun karyawan berhasil disetujui.',
            'user' => $user,
        ]);
    }

    public function rejectUser(Request $request, $id)
    {
        $reason = $request->input('alasan', $request->input('reason'));

        $user = User::where('id', $id)->orWhere('email', $id)->first();
        if ($user) {
            $user->update([
                'status' => 'REJECTED',
                'alasan_penolakan' => $reason,
            ]);
        }

        return response()->json([
            'message' => 'Pendaftaran karyawan telah ditolak.',
            'user' => $user,
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $currentUser = $request->user();
        $userRole = $currentUser ? strtoupper($currentUser->peran) : '';
        if (! $currentUser || ! in_array($userRole, ['SUPER_ADMIN', 'ADMIN'])) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin/Super Admin yang berhak menghapus karyawan.',
            ], 403);
        }

        // Cari user berdasarkan ID ataupun Email
        $user = User::where('id', $id)->orWhere('email', $id)->first();
        if (! $user) {
            // Jika user sudah tidak ada di database, anggap berhasil agar sinkron
            return response()->json([
                'message' => 'User sudah tidak ada di sistem database.',
                'id' => $id,
            ], 200);
        }

        if ($user->id === 'usr_super_admin' || $user->email === 'nasywadavina384@gmail.com' || strtoupper($user->peran) === 'SUPER_ADMIN') {
            return response()->json([
                'message' => 'Akun Super Admin utama tidak dapat dihapus.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $targetId = $user->id;

            // 1. Bersihkan tugas proyek (baik ditugaskan ke maupun dibuat oleh user ini)
            DB::table('tugas_proyek')
                ->where('ditugaskan_ke', $targetId)
                ->orWhere('dibuat_oleh', $targetId)
                ->delete();

            // 2. Bersihkan pesan obrolan di topik yang dibuat oleh user ini, lalu hapus topiknya
            $userTopicIds = DB::table('topik_diskusi')->where('dibuat_oleh_id', $targetId)->pluck('id');
            if ($userTopicIds->isNotEmpty()) {
                DB::table('pesan_diskusi')->whereIn('topik_id', $userTopicIds)->delete();
                DB::table('topik_diskusi')->whereIn('id', $userTopicIds)->delete();
            }

            // 3. Bersihkan seluruh pesan obrolan yang pernah dikirim oleh user ini
            DB::table('pesan_diskusi')->where('pengguna_id', $targetId)->delete();

            // 4. Bersihkan logbook harian
            DB::table('logbook_harian')->where('pengguna_id', $targetId)->delete();

            // 5. Bersihkan pengajuan izin
            DB::table('pengajuan_izin')->where('pengguna_id', $targetId)->delete();

            // 6. Bersihkan riwayat presensi
            DB::table('presensi')->where('pengguna_id', $targetId)->delete();

            // 7. Bersihkan personal access tokens (Sanctum)
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $targetId)
                ->delete();

            // 8. Hapus entri karyawan dari tabel pengguna
            DB::table('pengguna')->where('id', $targetId)->delete();

            DB::commit();

            return response()->json([
                'message' => 'Karyawan berhasil dihapus dari sistem dan database.',
                'id' => $targetId,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menghapus karyawan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }
}
