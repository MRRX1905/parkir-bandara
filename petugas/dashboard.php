<?php
/**
 * Dashboard Operasional Petugas Parkir
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Dashboard Monitoring';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

// Izin: Petugas, Admin, Supervisor
check_role(['petugas', 'admin', 'supervisor']);

$pdo = getDB();
$today = date('Y-m-d');

// Handler Edit Transaksi (Koreksi salah input dari Dashboard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jenis_kendaraan_id = (int)($_POST['jenis_kendaraan_id'] ?? 0);
    $plat_nomor = trim($_POST['plat_nomor'] ?? '');
    $ciri_kendaraan = trim($_POST['ciri_kendaraan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $waktu_masuk = trim($_POST['waktu_masuk'] ?? '');
    $status = trim($_POST['status'] ?? 'masih_parkir');

    if ($id <= 0 || $jenis_kendaraan_id <= 0) {
        set_flash('danger', 'Data koreksi tidak valid.');
    } else {
        try {
            $stmt_old = $pdo->prepare("SELECT * FROM parkir WHERE id = ?");
            $stmt_old->execute([$id]);
            $old = $stmt_old->fetch();

            if ($old) {
                $tanggal = $old['tanggal'];
                $datetime_masuk = $tanggal . ' ' . (strlen($waktu_masuk) == 5 ? $waktu_masuk . ':00' : $waktu_masuk);

                $stmt_up = $pdo->prepare("
                    UPDATE parkir SET 
                        jenis_kendaraan_id = ?,
                        plat_nomor = ?,
                        ciri_kendaraan = ?,
                        keterangan = ?,
                        waktu_masuk = ?,
                        datetime_masuk = ?,
                        status = ?
                    WHERE id = ?
                ");
                $stmt_up->execute([
                    $jenis_kendaraan_id,
                    $plat_nomor ? strtoupper($plat_nomor) : null,
                    $ciri_kendaraan ?: null,
                    $keterangan ?: null,
                    $waktu_masuk,
                    $datetime_masuk,
                    $status,
                    $id
                ]);

                log_aktivitas($pdo, "Koreksi data transaksi #{$id} ({$old['kode_transaksi']}) oleh " . $_SESSION['user_nama'], 'master');
                set_flash('success', "Transaksi <strong>{$old['kode_transaksi']}</strong> ({$old['nomor_karcis']}) berhasil dikoreksi.");
                header('Location: ' . BASE_URL . '/petugas/dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui transaksi: ' . $e->getMessage());
        }
    }
}

// Handler Hapus Transaksi (Jika salah input)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_transaksi'])) {
    $id = (int)($_POST['id'] ?? 0);
    $alasan = trim($_POST['alasan_hapus'] ?? 'Salah input data');

    if ($id <= 0) {
        set_flash('danger', 'ID transaksi tidak valid.');
    } else {
        try {
            $stmt_check = $pdo->prepare("SELECT kode_transaksi, nomor_karcis FROM parkir WHERE id = ?");
            $stmt_check->execute([$id]);
            $trx = $stmt_check->fetch();

            if ($trx) {
                $stmt_del = $pdo->prepare("DELETE FROM parkir WHERE id = ?");
                $stmt_del->execute([$id]);

                log_aktivitas($pdo, "Menghapus transaksi {$trx['kode_transaksi']} ({$trx['nomor_karcis']}) - Alasan: {$alasan}", 'master');
                set_flash('success', "Transaksi <strong>{$trx['kode_transaksi']}</strong> ({$trx['nomor_karcis']}) berhasil dihapus.");
                header('Location: ' . BASE_URL . '/petugas/dashboard.php');
                exit;
            }
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
}

// Master jenis kendaraan untuk modal koreksi
$stmt_all_jenis = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_options = $stmt_all_jenis->fetchAll();

// 1. Total Masuk Hari Ini
$stmt_masuk = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ?");
$stmt_masuk->execute([$today]);
$total_masuk_hari_ini = (int)$stmt_masuk->fetchColumn();

// 2. Total Keluar Hari Ini
$stmt_keluar = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) = ? AND status = 'sudah_keluar'");
$stmt_keluar->execute([$today]);
$total_keluar_hari_ini = (int)$stmt_keluar->fetchColumn();

// 3. Masih Berada di Area Parkir (Total Aktif Saat Ini)
$stmt_aktif = $pdo->query("SELECT COUNT(*) FROM parkir WHERE status = 'masih_parkir'");
$total_masih_parkir = (int)$stmt_aktif->fetchColumn();

// 4. Breakdown per jenis kendaraan yang masih di area
$stmt_breakdown = $pdo->query("
    SELECT j.nama_jenis, j.icon_class, j.warna_badge, COUNT(p.id) as total
    FROM jenis_kendaraan j
    LEFT JOIN parkir p ON j.id = p.jenis_kendaraan_id AND p.status = 'masih_parkir'
    WHERE j.status = 'aktif'
    GROUP BY j.id, j.nama_jenis, j.icon_class, j.warna_badge
    ORDER BY j.urutan ASC
");
$jenis_breakdown = $stmt_breakdown->fetchAll();

// 5. Transaksi Terbaru Hari Ini (10 Terakhir)
$stmt_recent = $pdo->prepare("
    SELECT p.*, j.nama_jenis, j.icon_class, u_masuk.nama as nama_petugas_masuk
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_masuk ON p.petugas_masuk_id = u_masuk.id
    WHERE p.tanggal = ?
    ORDER BY p.id DESC
    LIMIT 10
");
$stmt_recent->execute([$today]);
$recent_transaksi = $stmt_recent->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-gauge-high text-primary me-2"></i> Monitoring Parkir Real-Time
        </h1>
        <div class="page-header-subtitle">
            Aktivitas Operasional Area Parkir <?= AIRPORT_NAME ?> &bull; Tanggal: <strong><?= format_tanggal_indo($today) ?></strong>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/petugas/masuk.php" class="btn btn-primary shadow-sm" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-right-to-bracket me-1 text-warning"></i> Catat Masuk Baru
        </a>
        <a href="<?= BASE_URL ?>/petugas/keluar.php" class="btn btn-warning shadow-sm fw-bold text-dark">
            <i class="fa-solid fa-right-from-bracket me-1"></i> Pos Kendaraan Keluar
        </a>
    </div>
</div>

<!-- 3 Kartu Indikator Utama -->
<div class="row g-3 mb-4">
    <!-- Masuk Hari Ini -->
    <div class="col-md-4">
        <div class="stat-card stat-navy">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Kendaraan Masuk Hari Ini</div>
                    <div class="stat-value text-primary"><?= number_format($total_masuk_hari_ini) ?></div>
                </div>
                <div class="stat-icon bg-primary-subtle text-primary">
                    <i class="fa-solid fa-right-to-bracket"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="fa-regular fa-calendar-check me-1"></i> Tercatat sejak 00:00 WIB
            </div>
        </div>
    </div>

    <!-- Keluar Hari Ini -->
    <div class="col-md-4">
        <div class="stat-card stat-success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Kendaraan Keluar Hari Ini</div>
                    <div class="stat-value text-success"><?= number_format($total_keluar_hari_ini) ?></div>
                </div>
                <div class="stat-icon bg-success-subtle text-success">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <i class="fa-solid fa-circle-check me-1 text-success"></i> Transaksi telah diselesaikan
            </div>
        </div>
    </div>

    <!-- Sedang Berada di Area Parkir -->
    <div class="col-md-4">
        <div class="stat-card stat-warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Masih Berada di Area Parkir</div>
                    <div class="stat-value text-dark" style="color: #b45309 !important;"><?= number_format($total_masih_parkir) ?></div>
                </div>
                <div class="stat-icon bg-warning-subtle text-warning">
                    <i class="fa-solid fa-car-side"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <span class="badge bg-warning text-dark fw-bold">Live Status</span> Kendaraan aktif di bandara
            </div>
        </div>
    </div>
</div>

<!-- Komposisi Kendaraan yang Sedang Berada di Area Parkir -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-layer-group text-primary me-2"></i> Komposisi Kendaraan Aktif di Area Parkir
        </h6>
        <span class="badge bg-light text-secondary border">Total: <?= $total_masih_parkir ?> Unit</span>
    </div>
    <div class="card-body p-3">
        <div class="row g-2">
            <?php foreach ($jenis_breakdown as $jb): ?>
                <div class="col-6 col-md">
                    <div class="p-3 rounded-3 text-center border bg-light">
                        <i class="<?= e($jb['icon_class']) ?> fa-2x text-navy mb-2" style="color: #0b2e59;"></i>
                        <div class="fw-bold fs-4 text-dark"><?= $jb['total'] ?></div>
                        <div class="small text-muted text-truncate" title="<?= e($jb['nama_jenis']) ?>"><?= e($jb['nama_jenis']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tabel Aktivitas Terbaru Hari Ini -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i> 10 Aktivitas Transaksi Terakhir Hari Ini
        </h6>
        <a href="<?= BASE_URL ?>/petugas/data-parkir.php" class="btn btn-sm btn-outline-primary">
            Lihat Semua Transaksi <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover table-kemenhub align-middle">
            <thead>
                <tr>
                    <th>No. Karcis</th>
                    <th>Kode Transaksi</th>
                    <th>Jenis Kendaraan</th>
                    <th>Plat / Ciri</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Status</th>
                    <th class="text-center" style="min-width: 140px;">Aksi & Koreksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_transaksi)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                            Belum ada pencatatan kendaraan untuk hari ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_transaksi as $row): ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark-subtle text-dark fw-bold px-2 py-1"><?= e($row['nomor_karcis']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-primary font-monospace"><?= e($row['kode_transaksi']) ?></span>
                            </td>
                            <td>
                                <i class="<?= e($row['icon_class']) ?> me-1 text-secondary"></i>
                                <?= e($row['nama_jenis']) ?>
                            </td>
                            <td>
                                <?php if (!empty($row['plat_nomor'])): ?>
                                    <span class="badge bg-secondary text-white"><?= e($row['plat_nomor']) ?></span>
                                <?php endif; ?>
                                <span class="small text-muted d-block"><?= e($row['ciri_kendaraan'] ?? '-') ?></span>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-clock me-1 text-muted"></i><?= format_waktu($row['waktu_masuk']) ?></div>
                                <div class="small text-muted" style="font-size: 0.72rem;">Oleh: <?= e($row['nama_petugas_masuk']) ?></div>
                            </td>
                            <td>
                                <?= $row['waktu_keluar'] ? format_waktu($row['waktu_keluar']) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?= badge_status($row['status']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>/petugas/karcis.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Karcis">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                    <!-- Tombol Koreksi Edit -->
                                    <button type="button" class="btn btn-outline-primary btn-edit-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        data-jenis="<?= $row['jenis_kendaraan_id'] ?>"
                                        data-plat="<?= e($row['plat_nomor'] ?? '') ?>"
                                        data-ciri="<?= e($row['ciri_kendaraan'] ?? '') ?>"
                                        data-waktu="<?= substr($row['waktu_masuk'], 0, 5) ?>"
                                        data-status="<?= $row['status'] ?>"
                                        data-keterangan="<?= e($row['keterangan'] ?? '') ?>"
                                        title="Koreksi Salah Input">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <!-- Tombol Hapus -->
                                    <button type="button" class="btn btn-outline-danger btn-hapus-trx"
                                        data-id="<?= $row['id'] ?>"
                                        data-karcis="<?= e($row['nomor_karcis']) ?>"
                                        data-kode="<?= e($row['kode_transaksi']) ?>"
                                        title="Hapus Transaksi">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                    <?php if ($row['status'] === 'masih_parkir'): ?>
                                        <a href="<?= BASE_URL ?>/petugas/keluar.php?kode=<?= urlencode($row['kode_transaksi']) ?>" class="btn btn-success" title="Proses Keluar">
                                            <i class="fa-solid fa-right-from-bracket"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile Cards: 10 Transaksi Terakhir (d-block d-md-none) -->
<div class="d-block d-md-none px-2 pb-3">
    <?php if (empty($recent_transaksi)): ?>
        <div class="text-center py-4 text-muted">
            <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-50"></i>
            Belum ada pencatatan kendaraan untuk hari ini.
        </div>
    <?php else: ?>
        <?php foreach ($recent_transaksi as $row): ?>
            <div class="card border-0 shadow-sm rounded-3 mb-2">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-warning text-dark fw-bold px-2 py-1"><?= e($row['nomor_karcis']) ?></span>
                        <?= badge_status($row['status']) ?>
                    </div>
                    <div class="font-monospace text-primary fw-semibold small mb-1"><?= e($row['kode_transaksi']) ?></div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-light text-dark border">
                            <i class="<?= e($row['icon_class']) ?> me-1"></i><?= e($row['nama_jenis']) ?>
                        </span>
                        <?php if (!empty($row['plat_nomor'])): ?>
                            <span class="badge bg-dark text-white"><?= e($row['plat_nomor']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($row['ciri_kendaraan'])): ?>
                        <div class="small text-muted mb-2"><?= e($row['ciri_kendaraan']) ?></div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between small text-muted border-top pt-2">
                        <span><i class="fa-solid fa-right-to-bracket me-1 text-success"></i><?= format_waktu($row['waktu_masuk']) ?></span>
                        <span><i class="fa-solid fa-right-from-bracket me-1 text-danger"></i><?= $row['waktu_keluar'] ? format_waktu($row['waktu_keluar']) : '-' ?></span>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <a href="<?= BASE_URL ?>/petugas/karcis.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-print me-1"></i>Karcis
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-trx flex-grow-1"
                            data-id="<?= $row['id'] ?>"
                            data-karcis="<?= e($row['nomor_karcis']) ?>"
                            data-kode="<?= e($row['kode_transaksi']) ?>"
                            data-jenis="<?= $row['jenis_kendaraan_id'] ?>"
                            data-plat="<?= e($row['plat_nomor'] ?? '') ?>"
                            data-ciri="<?= e($row['ciri_kendaraan'] ?? '') ?>"
                            data-waktu="<?= substr($row['waktu_masuk'], 0, 5) ?>"
                            data-status="<?= $row['status'] ?>"
                            data-keterangan="<?= e($row['keterangan'] ?? '') ?>">
                            <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-trx"
                            data-id="<?= $row['id'] ?>"
                            data-karcis="<?= e($row['nomor_karcis']) ?>"
                            data-kode="<?= e($row['kode_transaksi']) ?>">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                        <?php if ($row['status'] === 'masih_parkir'): ?>
                            <a href="<?= BASE_URL ?>/petugas/keluar.php?kode=<?= urlencode($row['kode_transaksi']) ?>" class="btn btn-sm btn-success">
                                <i class="fa-solid fa-right-from-bracket"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal 1: Edit / Koreksi Transaksi -->
<div class="modal fade" id="modalEditTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i> Koreksi Transaksi Parkir
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_trx_id">
                
                <div class="modal-body p-4">
                    <div class="alert alert-light border d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="small text-muted d-block">Nomor Karcis</span>
                            <span class="badge bg-warning text-dark fs-6" id="edit_trx_karcis">K-000</span>
                        </div>
                        <div class="text-end">
                            <span class="small text-muted d-block">Kode Transaksi</span>
                            <span class="font-monospace fw-bold text-primary" id="edit_trx_kode">MLK-00000000-0000</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis Kendaraan <span class="text-danger">*</span></label>
                        <select name="jenis_kendaraan_id" id="edit_trx_jenis" class="form-select" required>
                            <?php foreach ($jenis_options as $jo): ?>
                                <option value="<?= $jo['id'] ?>"><?= e($jo['nama_jenis']) ?> (<?= e($jo['kode_kategori']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Plat Nomor</label>
                            <input type="text" name="plat_nomor" id="edit_trx_plat" class="form-control text-uppercase" placeholder="Contoh: BL 1234 AA">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Warna / Ciri Kendaraan</label>
                            <input type="text" name="ciri_kendaraan" id="edit_trx_ciri" class="form-control" placeholder="Contoh: Avanza Putih">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Waktu Masuk</label>
                            <input type="time" name="waktu_masuk" id="edit_trx_waktu" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Status Parkir</label>
                            <select name="status" id="edit_trx_status" class="form-select">
                                <option value="masih_parkir">Masih Parkir</option>
                                <option value="sudah_keluar">Sudah Keluar</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Keterangan / Keperluan</label>
                        <input type="text" name="keterangan" id="edit_trx_keterangan" class="form-control" placeholder="Contoh: Antar Tamu / Jemput Keluarga">
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_transaksi" value="1" class="btn btn-primary px-4 fw-bold" style="background: #0b2e59; border-color: #0b2e59;">
                        <i class="fa-solid fa-floppy-disk me-1 text-warning"></i> Simpan Koreksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Hapus Transaksi (Salah Input / Duplikat) -->
<div class="modal fade" id="modalHapusTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> Hapus Transaksi Parkir
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="hapus_trx_id">

                <div class="modal-body p-4 text-center">
                    <div class="text-danger mb-3">
                        <i class="fa-solid fa-trash-can fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Yakin Ingin Menghapus Data Ini?</h5>
                    <p class="text-muted small mb-3">
                        Transaksi <strong id="hapus_trx_karcis" class="text-dark"></strong> (<span id="hapus_trx_kode" class="font-monospace text-primary"></span>) akan dihapus secara permanen dari basis data.
                    </p>

                    <div class="text-start bg-light p-3 rounded-3 border mb-2">
                        <label class="form-label small fw-bold text-dark">Alasan Penghapusan <span class="text-danger">*</span></label>
                        <select name="alasan_hapus" class="form-select mb-2" required>
                            <option value="Salah input jenis kendaraan">Salah input jenis kendaraan</option>
                            <option value="Input ganda / transaksi duplikat">Input ganda / transaksi duplikat</option>
                            <option value="Kendaraan langsung putar balik (batal masuk)">Kendaraan langsung putar balik (batal masuk)</option>
                            <option value="Pengujian sistem / data percobaan">Pengujian sistem / data percobaan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                        <small class="text-muted">Aktivitas penghapusan akan tercatat pada log audit keamanan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="hapus_transaksi" value="1" class="btn btn-danger px-4 fw-bold shadow-sm">
                        <i class="fa-solid fa-trash-can me-1"></i> Ya, Hapus Permanen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Inisialisasi Modal Edit Dashboard
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditTrx'));
    document.querySelectorAll('.btn-edit-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_trx_id').value = this.getAttribute('data-id');
            document.getElementById('edit_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('edit_trx_kode').textContent = this.getAttribute('data-kode');
            document.getElementById('edit_trx_jenis').value = this.getAttribute('data-jenis');
            document.getElementById('edit_trx_plat').value = this.getAttribute('data-plat');
            document.getElementById('edit_trx_ciri').value = this.getAttribute('data-ciri');
            document.getElementById('edit_trx_waktu').value = this.getAttribute('data-waktu');
            document.getElementById('edit_trx_status').value = this.getAttribute('data-status');
            document.getElementById('edit_trx_keterangan').value = this.getAttribute('data-keterangan');
            modalEdit.show();
        });
    });

    // Inisialisasi Modal Hapus Dashboard
    const modalHapus = new bootstrap.Modal(document.getElementById('modalHapusTrx'));
    document.querySelectorAll('.btn-hapus-trx').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('hapus_trx_id').value = this.getAttribute('data-id');
            document.getElementById('hapus_trx_karcis').textContent = this.getAttribute('data-karcis');
            document.getElementById('hapus_trx_kode').textContent = this.getAttribute('data-kode');
            modalHapus.show();
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
