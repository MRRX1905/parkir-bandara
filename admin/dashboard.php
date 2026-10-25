<?php
/**
 * Dashboard Administrator Sistem
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Dashboard Administrator';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();
$today = date('Y-m-d');

// Metrik Global
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_jenis = (int)$pdo->query("SELECT COUNT(*) FROM jenis_kendaraan WHERE status = 'aktif'")->fetchColumn();
$masuk_hari_ini = (int)$pdo->query("SELECT COUNT(*) FROM parkir WHERE tanggal = '$today'")->fetchColumn();
$masih_parkir = (int)$pdo->query("SELECT COUNT(*) FROM parkir WHERE status = 'masih_parkir'")->fetchColumn();
$total_semua_transaksi = (int)$pdo->query("SELECT COUNT(*) FROM parkir")->fetchColumn();

// Log Aktivitas Terakhir
$stmt_log = $pdo->query("
    SELECT l.*, u.nama, u.role
    FROM log_aktivitas l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC
    LIMIT 8
");
$logs = $stmt_log->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-screwdriver-wrench text-danger me-2"></i> Panel Kendali Administrator
        </h1>
        <div class="page-header-subtitle">
            Pusat administrasi pengguna, master data, dan pemantauan sistem <?= AIRPORT_NAME ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-users me-1"></i> Kelola Petugas
        </a>
        <a href="<?= BASE_URL ?>/petugas/masuk.php" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-plus-circle me-1 text-warning"></i> Buka Pos Masuk
        </a>
    </div>
</div>

<!-- 4 Kartu Ringkasan Admin -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <div class="stat-label">Pengguna Terdaftar</div>
            <div class="stat-value text-primary"><?= $total_users ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-user-shield me-1"></i> Petugas & Supervisor
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-label">Masih Berada di Area</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= $masih_parkir ?></div>
            <div class="small text-muted mt-2">
                <span class="badge bg-warning text-dark">Live</span> Kendaraan aktif
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-label">Transaksi Hari Ini</div>
            <div class="stat-value text-success"><?= $masuk_hari_ini ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-regular fa-calendar-check me-1"></i> Tanggal <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-label">Total Riwayat Transaksi</div>
            <div class="stat-value text-info"><?= number_format($total_semua_transaksi) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-database me-1"></i> Seluruh data tersimpan
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Menu Cepat Admin -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-shapes text-primary me-2"></i> Akses Cepat Modul Sistem
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="d-grid gap-2">
                    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-light border text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark"><i class="fa-solid fa-users text-primary me-2"></i> Manajemen Pengguna</div>
                            <div class="small text-muted">Tambah akun petugas loket, ganti password, atur role</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/jenis-kendaraan.php" class="btn btn-light border text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark"><i class="fa-solid fa-car-tunnel text-warning me-2"></i> Master Jenis Kendaraan</div>
                            <div class="small text-muted">Atur jenis tarif/kategori (Motor, Mobil, Bus, Truk, Dinas)</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-light border text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark"><i class="fa-solid fa-file-invoice text-success me-2"></i> Rekapitulasi & Export</div>
                            <div class="small text-muted">Cetak laporan resmi berkop surat Kemenhub & download Excel</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/admin/pengaturan.php" class="btn btn-light border text-start p-3 rounded-3 d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold text-dark"><i class="fa-solid fa-sliders text-secondary me-2"></i> Profil & Konfigurasi Bandara</div>
                            <div class="small text-muted">Ubah nama instansi, alamat bandara, kontak & kode bandara</div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Audit Aktivitas Terbaru -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> Audit Trail & Log Aktivitas Terbaru
                </h6>
                <span class="badge bg-light text-muted border">Keamanan Data</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-kemenhub align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Aktivitas</th>
                            <th>Tipe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada aktivitas tercatat.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($l['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($l['nama'] ?: 'Sistem') ?></div>
                                        <small class="text-muted"><?= ucfirst($l['role'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <span class="small text-dark"><?= e($l['aktivitas']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= strtoupper($l['tipe']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
