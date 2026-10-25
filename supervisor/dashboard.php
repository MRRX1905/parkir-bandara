<?php
/**
 * Dashboard Monitoring Eksekutif Supervisor
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Dashboard Monitoring Supervisor';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['supervisor', 'admin']);

$pdo = getDB();
$today = date('Y-m-d');
$first_day_month = date('Y-m-01');

// 1. Metrik Hari Ini
$stmt_today = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN tanggal = ? THEN 1 END) as masuk_hari_ini,
        COUNT(CASE WHEN DATE(datetime_keluar) = ? AND status = 'sudah_keluar' THEN 1 END) as keluar_hari_ini,
        COUNT(CASE WHEN status = 'masih_parkir' THEN 1 END) as aktif_saat_ini,
        AVG(CASE WHEN tanggal = ? AND status = 'sudah_keluar' THEN durasi_menit END) as rata_durasi_hari_ini
    FROM parkir
");
$stmt_today->execute([$today, $today, $today]);
$metric_today = $stmt_today->fetch();

// 2. Metrik Bulan Ini
$stmt_month = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bulan_ini,
        COUNT(CASE WHEN status = 'sudah_keluar' THEN 1 END) as keluar_bulan_ini
    FROM parkir
    WHERE tanggal BETWEEN ? AND ?
");
$stmt_month->execute([$first_day_month, $today]);
$metric_month = $stmt_month->fetch();

// 3. Data Grafik Aktivitas per Jam Hari Ini (06:00 - 18:00)
$hours = ['06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
$chart_masuk = [];
$chart_keluar = [];

foreach ($hours as $h) {
    $h_start = substr($h, 0, 2) . ':00:00';
    $h_end = substr($h, 0, 2) . ':59:59';

    // Masuk per jam
    $stmt_h_in = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ? AND waktu_masuk BETWEEN ? AND ?");
    $stmt_h_in->execute([$today, $h_start, $h_end]);
    $chart_masuk[] = (int)$stmt_h_in->fetchColumn();

    // Keluar per jam
    $stmt_h_out = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE DATE(datetime_keluar) = ? AND waktu_keluar BETWEEN ? AND ?");
    $stmt_h_out->execute([$today, $h_start, $h_end]);
    $chart_keluar[] = (int)$stmt_h_out->fetchColumn();
}

// 4. Komposisi Jenis Kendaraan (Untuk Donut Chart)
$stmt_pie = $pdo->prepare("
    SELECT j.nama_jenis, COUNT(p.id) as total
    FROM jenis_kendaraan j
    LEFT JOIN parkir p ON j.id = p.jenis_kendaraan_id AND p.tanggal BETWEEN ? AND ?
    GROUP BY j.id, j.nama_jenis
    ORDER BY j.urutan ASC
");
$stmt_pie->execute([$first_day_month, $today]);
$pie_data = $stmt_pie->fetchAll();

$pie_labels = [];
$pie_values = [];
foreach ($pie_data as $pd) {
    $pie_labels[] = $pd['nama_jenis'];
    $pie_values[] = (int)$pd['total'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-chart-pie text-primary me-2"></i> Dashboard Monitoring Eksekutif
        </h1>
        <div class="page-header-subtitle">
            Ringkasan data analitik & pemantauan operasional parkir <?= AIRPORT_NAME ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/supervisor/laporan.php" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
            <i class="fa-solid fa-file-invoice me-1"></i> Rekapitulasi Laporan
        </a>
    </div>
</div>

<!-- 4 Kartu Metrik Utama -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card stat-navy">
            <div class="stat-label">Masuk Hari Ini</div>
            <div class="stat-value text-primary"><?= number_format($metric_today['masuk_hari_ini'] ?? 0) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-regular fa-calendar me-1"></i> <?= format_tanggal_indo($today) ?>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-success">
            <div class="stat-label">Keluar Hari Ini</div>
            <div class="stat-value text-success"><?= number_format($metric_today['keluar_hari_ini'] ?? 0) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-circle-check me-1 text-success"></i> Selesai di Pos Keluar
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-warning">
            <div class="stat-label">Aktif di Area Saat Ini</div>
            <div class="stat-value" style="color: #b45309 !important;"><?= number_format($metric_today['aktif_saat_ini'] ?? 0) ?></div>
            <div class="small text-muted mt-2">
                <span class="badge bg-warning text-dark">Real-Time</span> Masih terparkir
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card stat-info">
            <div class="stat-label">Rata-Rata Durasi Parkir</div>
            <div class="stat-value text-info" style="font-size: 1.6rem;"><?= format_durasi(round($metric_today['rata_durasi_hari_ini'] ?? 0)) ?></div>
            <div class="small text-muted mt-2">
                <i class="fa-solid fa-clock me-1 text-info"></i> Durasi kunjungan hari ini
            </div>
        </div>
    </div>
</div>

<!-- 2 Grafik Pemantauan -->
<div class="row g-4 mb-4">
    <!-- Grafik Lalu Lintas per Jam -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-chart-line text-primary me-2"></i> Fluktuasi Lalu Lintas Kendaraan per Jam (Hari Ini)
                </h6>
                <span class="badge bg-light text-muted border">WIB</span>
            </div>
            <div class="card-body p-4">
                <canvas id="trafficHourlyChart" height="240"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Donut Komposisi Jenis Kendaraan -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fa-solid fa-chart-pie text-primary me-2"></i> Proporsi Jenis Kendaraan
                </h6>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <div style="max-width: 250px; width: 100%;">
                    <canvas id="pieJenisChart"></canvas>
                </div>
                <div class="mt-3 text-center small text-muted">
                    Total Aktivitas Bulan Ini: <strong><?= number_format($metric_month['total_bulan_ini'] ?? 0) ?></strong> Kendaraan
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Chart Traffic Hourly
    const ctxTraffic = document.getElementById('trafficHourlyChart').getContext('2d');
    new Chart(ctxTraffic, {
        type: 'line',
        data: {
            labels: <?= json_encode($hours) ?>,
            datasets: [
                {
                    label: 'Kendaraan Masuk',
                    data: <?= json_encode($chart_masuk) ?>,
                    borderColor: '#0b2e59',
                    backgroundColor: 'rgba(11, 46, 89, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#0b2e59',
                    pointRadius: 4
                },
                {
                    label: 'Kendaraan Keluar',
                    data: <?= json_encode($chart_keluar) ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                legend: { position: 'top' }
            }
        }
    });

    // 2. Chart Pie Jenis Kendaraan
    const ctxPie = document.getElementById('pieJenisChart').getContext('2d');
    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pie_labels) ?>,
            datasets: [{
                data: <?= json_encode($pie_values) ?>,
                backgroundColor: [
                    '#0ea5e9', // Motor (Sky)
                    '#0b2e59', // Mobil (Navy)
                    '#f59e0b', // Bus (Gold)
                    '#64748b', // Truk (Slate)
                    '#10b981'  // Dinas (Green)
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            cutout: '65%'
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
