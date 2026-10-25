<?php
/**
 * Sidebar Navigasi Berdasarkan Role
 */
$user_role = $_SESSION['user_role'] ?? 'petugas';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<aside class="app-sidebar d-none d-lg-flex">
    <div class="py-3">
        <!-- Menu Navigasi Utama -->
        <div class="sidebar-heading">Menu Utama</div>
        <ul class="sidebar-nav">
            <?php if ($user_role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_dir === 'admin' && $current_page === 'dashboard.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/dashboard.php">
                        <i class="fa-solid fa-chart-line"></i> Dashboard Admin
                    </a>
                </li>
            <?php elseif ($user_role === 'supervisor'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_dir === 'supervisor' && $current_page === 'dashboard.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/supervisor/dashboard.php">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard Supervisor
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_dir === 'petugas' && $current_page === 'dashboard.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/petugas/dashboard.php">
                        <i class="fa-solid fa-desktop"></i> Dashboard Petugas
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Operasional Parkir (Untuk Petugas & Admin) -->
        <?php if ($user_role === 'petugas' || $user_role === 'admin'): ?>
            <div class="sidebar-heading">Operasional Pos</div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'masuk.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/petugas/masuk.php">
                        <i class="fa-solid fa-right-to-bracket text-primary"></i> Kendaraan Masuk
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'keluar.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/petugas/keluar.php">
                        <i class="fa-solid fa-right-from-bracket text-warning"></i> Kendaraan Keluar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'data-parkir.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/petugas/data-parkir.php">
                        <i class="fa-solid fa-clock-rotate-left"></i> Data Transaksi Parkir
                    </a>
                </li>
            </ul>
        <?php endif; ?>

        <!-- Laporan & Rekapitulasi (Semua Role atau Supervisor/Admin) -->
        <div class="sidebar-heading">Laporan & Rekap</div>
        <ul class="sidebar-nav">
            <?php if ($user_role === 'supervisor'): ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_dir === 'supervisor' && $current_page === 'laporan.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/supervisor/laporan.php">
                        <i class="fa-solid fa-file-invoice"></i> Rekapitulasi Laporan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'data-parkir.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/petugas/data-parkir.php">
                        <i class="fa-solid fa-list-check"></i> Riwayat Kendaraan
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'laporan.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/supervisor/laporan.php">
                        <i class="fa-solid fa-file-invoice"></i> Laporan Aktivitas
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Pengaturan & Master Data (Khusus Admin) -->
        <?php if ($user_role === 'admin'): ?>
            <div class="sidebar-heading">Master Data & Sistem</div>
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'users.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/users.php">
                        <i class="fa-solid fa-users-gear"></i> Kelola Pengguna
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'jenis-kendaraan.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/jenis-kendaraan.php">
                        <i class="fa-solid fa-car-tunnel"></i> Jenis Kendaraan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page === 'pengaturan.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/pengaturan.php">
                        <i class="fa-solid fa-sliders"></i> Pengaturan Bandara
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Status Pos di Sidebar Bawah -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <span class="small fw-semibold text-secondary">Status Sistem:</span>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                <i class="fa-solid fa-circle fa-2xs me-1"></i> Online (WIB)
            </span>
        </div>
        <div class="text-muted" style="font-size: 0.72rem;">
            <?= AIRPORT_NAME ?><br>
            Terminal Area Parkir
        </div>
    </div>
</aside>
