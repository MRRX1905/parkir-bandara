<?php
/**
 * Manajemen Pengguna Sistem (Users)
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Manajemen Pengguna';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();

// 1. Tambah Pengguna Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    $nama = trim($_POST['nama'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $nip = trim($_POST['nip_nik'] ?? '');
    $hp = trim($_POST['no_hp'] ?? '');

    if (empty($nama) || empty($username) || empty($password)) {
        set_flash('danger', 'Nama, username, dan password wajib diisi.');
    } else {
        try {
            // Cek apakah username sudah ada
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt_cek->execute([$username]);
            if ($stmt_cek->fetchColumn() > 0) {
                set_flash('danger', 'Username sudah digunakan oleh akun lain. Gunakan username berbeda.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $pdo->prepare("INSERT INTO users (nama, username, password, role, nip_nik, no_hp, status) VALUES (?, ?, ?, ?, ?, ?, 'aktif')");
                $stmt_insert->execute([$nama, $username, $hash, $role, $nip ?: null, $hp ?: null]);

                log_aktivitas($pdo, "Menambahkan pengguna baru: {$nama} ({$username}) role {$role}", 'master');
                set_flash('success', "Akun pengguna <strong>" . e($nama) . "</strong> berhasil dibuat.");
                header('Location: ' . BASE_URL . '/admin/users.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menambahkan akun: ' . $e->getMessage());
        }
    }
}

// 2. Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $role = trim($_POST['role'] ?? 'petugas');
    $nip = trim($_POST['nip_nik'] ?? '');
    $hp = trim($_POST['no_hp'] ?? '');
    $status = trim($_POST['status'] ?? 'aktif');

    if ($id <= 0 || empty($nama)) {
        set_flash('danger', 'Data tidak valid.');
    } else {
        try {
            $stmt_update = $pdo->prepare("UPDATE users SET nama = ?, role = ?, nip_nik = ?, no_hp = ?, status = ? WHERE id = ?");
            $stmt_update->execute([$nama, $role, $nip ?: null, $hp ?: null, $status, $id]);

            log_aktivitas($pdo, "Memperbarui profil pengguna ID: {$id} ({$nama})", 'master');
            set_flash('success', "Data pengguna <strong>" . e($nama) . "</strong> berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui pengguna: ' . $e->getMessage());
        }
    }
}

// 3. Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id = (int)($_POST['id'] ?? 0);
    $new_pass = trim($_POST['new_password'] ?? '');

    if ($id <= 0 || empty($new_pass)) {
        set_flash('danger', 'Password baru tidak boleh kosong.');
    } else {
        try {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt_pw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt_pw->execute([$hash, $id]);

            log_aktivitas($pdo, "Mereset password pengguna ID: {$id}", 'master');
            set_flash('success', "Password pengguna berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/users.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal mereset password: ' . $e->getMessage());
        }
    }
}

// Ambil daftar pengguna
$stmt_users = $pdo->query("SELECT * FROM users ORDER BY role ASC, nama ASC");
$users = $stmt_users->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-users-gear text-primary me-2"></i> Manajemen Akun Pengguna
        </h1>
        <div class="page-header-subtitle">
            Kelola data akun petugas pos, supervisor, dan administrator sistem
        </div>
    </div>
    <button type="button" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
        <i class="fa-solid fa-user-plus me-1 text-warning"></i> Tambah Pengguna Baru
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Nama Petugas</th>
                    <th>Username</th>
                    <th>NIP / NIK</th>
                    <th>No. Telepon / HP</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Login Terakhir</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark"><?= e($u['nama']) ?></div>
                            <small class="text-muted">ID: #<?= $u['id'] ?></small>
                        </td>
                        <td>
                            <code><?= e($u['username']) ?></code>
                        </td>
                        <td><?= e($u['nip_nik'] ?? '-') ?></td>
                        <td><?= e($u['no_hp'] ?? '-') ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Administrator</span>
                            <?php elseif ($u['role'] === 'supervisor'): ?>
                                <span class="badge bg-warning text-dark">Supervisor</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Petugas Pos</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'aktif'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="text-muted"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Belum pernah' ?></small>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-edit-user"
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= e($u['nama']) ?>"
                                    data-role="<?= e($u['role']) ?>"
                                    data-nip="<?= e($u['nip_nik'] ?? '') ?>"
                                    data-hp="<?= e($u['no_hp'] ?? '') ?>"
                                    data-status="<?= e($u['status']) ?>"
                                    title="Edit Profil">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning text-dark btn-pw-user"
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= e($u['nama']) ?>"
                                    title="Ganti Password">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah User -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus text-warning me-2"></i> Tambah Pengguna Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Rahmat Hidayat" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="Contoh: rahmat" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Role / Hak Akses <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="petugas">Petugas Pos (Operasional Masuk & Keluar)</option>
                            <option value="supervisor">Supervisor (Monitoring & Laporan)</option>
                            <option value="admin">Administrator (Akses Penuh)</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">NIP / NIK</label>
                            <input type="text" name="nip_nik" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Nomor WhatsApp / HP</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxx">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_user" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-pen text-warning me-2"></i> Edit Data Pengguna</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Role Hak Akses</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="petugas">Petugas Pos</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Status Akun</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">NIP / NIK</label>
                            <input type="text" name="nip_nik" id="edit_nip" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Nomor HP</label>
                            <input type="text" name="no_hp" id="edit_hp" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_user" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ganti Password -->
<div class="modal fade" id="modalPasswordUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning text-dark py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-key me-2"></i> Reset Password Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="pw_id">
                <div class="modal-body p-4">
                    <p class="text-muted small">Atur ulang kata sandi untuk akun <strong id="pw_nama" class="text-dark"></strong>:</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Ketik kata sandi baru" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="reset_password" value="1" class="btn btn-warning fw-bold text-dark">
                        Perbarui Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit User Modal
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditUser'));
    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_role').value = this.getAttribute('data-role');
            document.getElementById('edit_nip').value = this.getAttribute('data-nip');
            document.getElementById('edit_hp').value = this.getAttribute('data-hp');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            modalEdit.show();
        });
    });

    // Password User Modal
    const modalPw = new bootstrap.Modal(document.getElementById('modalPasswordUser'));
    document.querySelectorAll('.btn-pw-user').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('pw_id').value = this.getAttribute('data-id');
            document.getElementById('pw_nama').textContent = this.getAttribute('data-nama');
            modalPw.show();
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
