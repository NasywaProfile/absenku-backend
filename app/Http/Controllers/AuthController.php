<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
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

        return response()->json([
            'message' => 'Pendaftaran berhasil. Akun Anda sedang menunggu persetujuan Super Admin.',
            'user' => $user,
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

        $nama = $request->input('nama', $request->input('name', $user->nama));
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

        if ($request->has('password') && ! empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    public function getUsers(Request $request)
    {
        $users = User::orderBy('nama')->get();
        return response()->json($users);
    }

    public function approveUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'status' => 'ACTIVE',
        ]);

        return response()->json([
            'message' => 'Akun karyawan berhasil disetujui.',
            'user' => $user,
        ]);
    }

    public function rejectUser(Request $request, $id)
    {
        $reason = $request->input('alasan', $request->input('reason'));

        $user = User::findOrFail($id);
        $user->update([
            'status' => 'REJECTED',
            'alasan_penolakan' => $reason,
        ]);

        return response()->json([
            'message' => 'Pendaftaran karyawan telah ditolak.',
            'user' => $user,
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->peran, ['SUPER_ADMIN', 'ADMIN'])) {
            return response()->json([
                'message' => 'Unauthorized. Hanya Admin/Super Admin yang berhak menghapus karyawan.',
            ], 403);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        if ($user->id === 'usr_super_admin' || $user->email === 'nasywadavina384@gmail.com' || $user->peran === 'SUPER_ADMIN') {
            return response()->json([
                'message' => 'Akun Super Admin tidak dapat dihapus.',
            ], 403);
        }

        // Hapus relasi terkait yang tidak memiliki ON DELETE CASCADE otomatis
        \App\Models\KanbanTask::where('ditugaskan_ke', $id)->delete();
        \App\Models\ChatTopic::where('dibuat_oleh_id', $id)->delete();

        // Hapus tokens dan user (presensi, pengajuan_izin, logbook_harian, pesan_diskusi cascade otomatis via DB foreign keys)
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Karyawan berhasil dihapus dari sistem dan database.',
            'id' => $id,
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Berhasil logout.',
        ]);
    }
}
