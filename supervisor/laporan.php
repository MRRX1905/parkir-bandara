<?php
/**
 * Laporan & Rekapitulasi Aktivitas Parkir
 * Bandara Malikussaleh (LSW)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['supervisor', 'admin', 'petugas']);

$pdo = getDB();

// Parameter Filter
$tgl_mulai = trim($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));
$jenis_id = (int)($_GET['jenis_id'] ?? 0);
$status = trim($_GET['status'] ?? '');
$is_export = isset($_GET['export']) && $_GET['export'] === 'excel';
$is_print = isset($_GET['print']) && $_GET['print'] === '1';

// Query dasar data
$sql = "
    SELECT p.*, j.nama_jenis, j.icon_class,
           u_masuk.nama as nama_petugas_masuk,
           u_keluar.nama as nama_petugas_keluar
    FROM parkir p
    JOIN jenis_kendaraan j ON p.jenis_kendaraan_id = j.id
    LEFT JOIN users u_masuk ON p.petugas_masuk_id = u_masuk.id
    LEFT JOIN users u_keluar ON p.petugas_keluar_id = u_keluar.id
    WHERE p.tanggal BETWEEN ? AND ?
";
$params = [$tgl_mulai, $tgl_selesai];

if ($jenis_id > 0) {
    $sql .= " AND p.jenis_kendaraan_id = ?";
    $params[] = $jenis_id;
}

if (!empty($status)) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.datetime_masuk ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Hitung Rekapitulasi per Jenis Kendaraan
$rekap_jenis = [];
$total_masuk = count($data);
$total_keluar = 0;
$total_masih_parkir = 0;
$total_durasi = 0;
$count_durasi = 0;

foreach ($data as $d) {
    $j_name = $d['nama_jenis'];
    if (!isset($rekap_jenis[$j_name])) {
        $rekap_jenis[$j_name] = ['masuk' => 0, 'keluar' => 0, 'masih' => 0];
    }
    $rekap_jenis[$j_name]['masuk']++;

    if ($d['status'] === 'sudah_keluar') {
        $total_keluar++;
        $rekap_jenis[$j_name]['keluar']++;
        if ($d['durasi_menit'] !== null) {
            $total_durasi += $d['durasi_menit'];
            $count_durasi++;
        }
    } else {
        $total_masih_parkir++;
        $rekap_jenis[$j_name]['masih']++;
    }
}

$rata_durasi = $count_durasi > 0 ? round($total_durasi / $count_durasi) : 0;

// Export ke Excel / Spreadsheet
if ($is_export) {
    $filename = "Laporan_Parkir_Malikussaleh_{$tgl_mulai}_sd_{$tgl_selesai}.xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1">
        <tr>
            <th colspan="10" style="font-size: 16px; font-weight: bold; text-align: center;">
                LAPORAN AKTIVITAS KENDARAAN MASUK & KELUAR<br>
                <?= INSTANSI_NAME ?> - <?= UPBU_NAME ?> (<?= AIRPORT_CODE ?>)<br>
                Periode: <?= format_tanggal_indo($tgl_mulai) ?> s/d <?= format_tanggal_indo($tgl_selesai) ?>
            </th>
        </tr>
        <tr><td colspan="10"></td></tr>
        <tr style="background-color: #0b2e59; color: #ffffff;">
            <th>No</th>
            <th>No. Karcis</th>
            <th>Kode Transaksi</th>
            <th>Tanggal</th>
            <th>Jenis Kendaraan</th>
            <th>Plat Nomor</th>
            <th>Ciri Kendaraan</th>
            <th>Waktu Masuk</th>
            <th>Waktu Keluar</th>
            <th>Durasi (Menit)</th>
            <th>Status</th>
            <th>Petugas Masuk</th>
            <th>Petugas Keluar</th>
        </tr>
        <?php $no = 1; foreach ($data as $r): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= e($r['nomor_karcis']) ?></td>
            <td><?= e($r['kode_transaksi']) ?></td>
            <td><?= $r['tanggal'] ?></td>
            <td><?= e($r['nama_jenis']) ?></td>
            <td><?= e($r['plat_nomor'] ?? '-') ?></td>
            <td><?= e($r['ciri_kendaraan'] ?? '-') ?></td>
            <td><?= $r['waktu_masuk'] ?></td>
            <td><?= $r['waktu_keluar'] ?? '-' ?></td>
            <td><?= $r['durasi_menit'] ?? '-' ?></td>
            <td><?= $r['status'] === 'sudah_keluar' ? 'Sudah Keluar' : 'Masih Parkir' ?></td>
            <td><?= e($r['nama_petugas_masuk']) ?></td>
            <td><?= e($r['nama_petugas_keluar'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
    exit;
}

// Mode Print Khusus (Halaman Cetak Tanpa Sidebar/Navbar)
if ($is_print) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Cetak Laporan Parkir - <?= AIRPORT_NAME ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { font-family: 'Times New Roman', Times, serif; color: #000; font-size: 12pt; background: #fff; }
            .kop-surat { border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 24px; text-align: center; }
            .kop-surat h4 { margin: 0; font-size: 14pt; font-weight: bold; text-transform: uppercase; }
            .kop-surat h5 { margin: 2px 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; }
            .kop-surat p { margin: 0; font-size: 10pt; }
            table { width: 100%; border-collapse: collapse; font-size: 10pt; }
            table th, table td { border: 1px solid #000; padding: 6px 8px; }
            table th { background-color: #f2f2f2; text-align: center; }
            .no-print { margin: 20px 0; }
            @media print { .no-print { display: none !important; } }
        </style>
    </head>
    <body onload="window.print()">
        <div class="container my-4">
            <div class="no-print text-end">
                <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print me-1"></i> Cetak</button>
                <button onclick="window.close()" class="btn btn-secondary btn-sm ms-1">Tutup</button>
            </div>

            <!-- Kop Surat Resmi Kemenhub -->
            <div class="kop-surat">
                <h4><?= INSTANSI_NAME ?></h4>
                <h5><?= INSTANSI_SUB ?></h5>
                <h5><?= UPBU_NAME ?></h5>
                <p><?= AIRPORT_ADDRESS ?></p>
            </div>

            <div class="text-center mb-4">
                <h5 class="fw-bold text-uppercase mb-1" style="text-decoration: underline;">REKAPITULASI AKTIVITAS PARKIR KENDARAAN</h5>
                <div>Periode: <strong><?= format_tanggal_indo($tgl_mulai) ?></strong> s/d <strong><?= format_tanggal_indo($tgl_selesai) ?></strong></div>
            </div>

            <!-- Ringkasan Singkat -->
            <div class="row g-2 mb-4">
                <div class="col-3">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Total Kendaraan Masuk</small>
                        <strong><?= $total_masuk ?> Unit</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Total Kendaraan Keluar</small>
                        <strong><?= $total_keluar ?> Unit</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Masih di Area Parkir</small>
                        <strong><?= $total_masih_parkir ?> Unit</strong>
                    </div>
                </div>
                <div class="col-3">
                    <div class="p-2 border text-center">
                        <small class="d-block text-muted">Rata-rata Durasi Parkir</small>
                        <strong><?= format_durasi($rata_durasi) ?></strong>
                    </div>
                </div>
            </div>

            <!-- Tabel Data -->
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th>No. Karcis</th>
                        <th>Kode Transaksi</th>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Plat / Ciri</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Durasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                        <tr><td colspan="10" class="text-center py-3">Tidak ada data transaksi pada periode ini.</td></tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($data as $r): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center font-monospace"><strong><?= e($r['nomor_karcis']) ?></strong></td>
                                <td class="font-monospace"><?= e($r['kode_transaksi']) ?></td>
                                <td class="text-center"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                                <td><?= e($r['nama_jenis']) ?></td>
                                <td><?= e($r['plat_nomor'] ?: $r['ciri_kendaraan'] ?: '-') ?></td>
                                <td class="text-center"><?= format_waktu($r['waktu_masuk']) ?></td>
                                <td class="text-center"><?= $r['waktu_keluar'] ? format_waktu($r['waktu_keluar']) : '-' ?></td>
                                <td class="text-center"><?= format_durasi($r['durasi_menit']) ?></td>
                                <td class="text-center"><?= $r['status'] === 'sudah_keluar' ? 'Sudah Keluar' : 'Masih Parkir' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Tanda Tangan Pengesahan -->
            <div class="row mt-5 pt-4">
                <div class="col-6 text-center">
                    <div>Mengetahui,</div>
                    <div class="fw-bold">Supervisor Operasional Parkir</div>
                    <div style="height: 70px;"></div>
                    <div class="fw-bold" style="text-decoration: underline;">( ............................................ )</div>
                    <div class="small">NIP. ........................................</div>
                </div>
                <div class="col-6 text-center">
                    <div>Aceh Utara, <?= format_tanggal_indo(date('Y-m-d')) ?></div>
                    <div class="fw-bold">Petugas Pencatat Pos</div>
                    <div style="height: 70px;"></div>
                    <div class="fw-bold" style="text-decoration: underline;">( <?= e($_SESSION['user_nama']) ?> )</div>
                    <div class="small">Petugas Pelaksana Parkir</div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Tampilan Normal Web
$page_title = 'Rekapitulasi Laporan Parkir';
require_once __DIR__ . '/../includes/header.php';

// Ambil master jenis kendaraan
$stmt_j = $pdo->query("SELECT * FROM jenis_kendaraan ORDER BY urutan ASC");
$jenis_opts = $stmt_j->fetchAll();
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-file-invoice text-primary me-2"></i> Rekapitulasi & Laporan Aktivitas Parkir
        </h1>
        <div class="page-header-subtitle">
            Pelaporan operasional berkala kendaraan masuk dan keluar Bandara Malikussaleh
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="?<?= http_build_query(array_merge($_GET, ['print' => 1])) ?>" target="_blank" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-print me-1"></i> Cetak Laporan Resmi
        </a>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-success">
            <i class="fa-solid fa-file-excel me-1"></i> Export Excel
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
        <form action="" method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="tgl_mulai" class="form-label small fw-semibold text-dark">Dari Tanggal</label>
                <input type="date" class="form-control" id="tgl_mulai" name="tgl_mulai" value="<?= e($tgl_mulai) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="tgl_selesai" class="form-label small fw-semibold text-dark">Sampai Tanggal</label>
                <input type="date" class="form-control" id="tgl_selesai" name="tgl_selesai" value="<?= e($tgl_selesai) ?>" required>
            </div>
            <div class="col-md-3">
                <label for="jenis_id" class="form-label small fw-semibold text-dark">Jenis Kendaraan</label>
                <select class="form-select" id="jenis_id" name="jenis_id">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis_opts as $jo): ?>
                        <option value="<?= $jo['id'] ?>" <?= $jenis_id == $jo['id'] ? 'selected' : '' ?>>
                            <?= e($jo['nama_jenis']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label small fw-semibold text-dark">Status Kendaraan</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="masih_parkir" <?= $status === 'masih_parkir' ? 'selected' : '' ?>>Masih Parkir</option>
                    <option value="sudah_keluar" <?= $status === 'sudah_keluar' ? 'selected' : '' ?>>Sudah Keluar</option>
                </select>
            </div>
            <div class="col-12 text-end pt-2">
                <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-outline-secondary me-2">Reset</a>
                <button type="submit" class="btn btn-primary px-4" style="background: #0b2e59; border-color: #0b2e59;">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Tampilkan Rekapitulasi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4 Kotak Ringkasan Periode Terpilih -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <div class="stat-label">Total Masuk Periode Ini</div>
            <div class="stat-value text-primary"><?= number_format($total_masuk) ?></div>
            <div class="small text-muted mt-2">Unit kendaraan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-label">Total Selesai Keluar</div>
            <div class="stat-value text-success"><?= number_format($total_keluar) ?></div>
            <div class="small text-muted mt-2">Unit kendaraan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-label">Masih di Area Parkir</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= number_format($total_masih_parkir) ?></div>
            <div class="small text-muted mt-2">Unit kendaraan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-label">Rata-Rata Durasi Parkir</div>
            <div class="stat-value text-info" style="font-size: 1.5rem;"><?= format_durasi($rata_durasi) ?></div>
            <div class="small text-muted mt-2">Waktu kunjungan</div>
        </div>
    </div>
</div>

<!-- Tabel Rekap Per Jenis Kendaraan -->
<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-chart-column text-primary me-2"></i> Rekapitulasi Berdasarkan Jenis Kendaraan
        </h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>Jenis Kendaraan</th>
                    <th class="text-center">Total Masuk</th>
                    <th class="text-center">Sudah Keluar</th>
                    <th class="text-center">Masih Parkir</th>
                    <th class="text-center">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rekap_jenis)): ?>
                    <tr><td colspan="5" class="text-center py-3 text-muted">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rekap_jenis as $j_nama => $counts): 
                        $pct = $total_masuk > 0 ? round(($counts['masuk'] / $total_masuk) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= e($j_nama) ?></td>
                            <td class="text-center fw-semibold text-primary"><?= $counts['masuk'] ?> Unit</td>
                            <td class="text-center fw-semibold text-success"><?= $counts['keluar'] ?> Unit</td>
                            <td class="text-center fw-semibold text-warning"><?= $counts['masih'] ?> Unit</td>
                            <td class="text-center">
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; max-width: 120px;">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <small class="text-muted fw-bold"><?= $pct ?>%</small>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Tabel Rincian Data Transaksi -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark">
            <i class="fa-solid fa-list text-primary me-2"></i> Rincian Seluruh Transaksi Periode Terpilih
        </h6>
        <span class="badge bg-light text-secondary border">Total: <?= count($data) ?> Data</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>No. Karcis</th>
                    <th>Kode Transaksi</th>
                    <th>Tanggal</th>
                    <th>Jenis Kendaraan</th>
                    <th>Plat / Ciri</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Durasi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr><td colspan="10" class="text-center py-4 text-muted">Tidak ada data pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($data as $r): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><span class="badge bg-secondary-subtle text-dark fw-bold"><?= e($r['nomor_karcis']) ?></span></td>
                            <td class="font-monospace text-primary fw-semibold"><?= e($r['kode_transaksi']) ?></td>
                            <td><small><?= date('d/m/Y', strtotime($r['tanggal'])) ?></small></td>
                            <td><i class="<?= e($r['icon_class']) ?> me-1 text-secondary"></i> <?= e($r['nama_jenis']) ?></td>
                            <td>
                                <?php if (!empty($r['plat_nomor'])): ?>
                                    <span class="badge bg-dark text-white me-1"><?= e($r['plat_nomor']) ?></span>
                                <?php endif; ?>
                                <span class="small text-muted"><?= e($r['ciri_kendaraan'] ?? '-') ?></span>
                            </td>
                            <td>
                                <div><?= format_waktu($r['waktu_masuk']) ?></div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?= e($r['nama_petugas_masuk']) ?></small>
                            </td>
                            <td>
                                <?php if ($r['waktu_keluar']): ?>
                                    <div><?= format_waktu($r['waktu_keluar']) ?></div>
                                    <small class="text-muted" style="font-size: 0.72rem;"><?= e($r['nama_petugas_keluar'] ?? '-') ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_durasi($r['durasi_menit']) ?></td>
                            <td><?= badge_status($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
