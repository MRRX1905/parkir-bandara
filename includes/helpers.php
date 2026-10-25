<?php
/**
 * Helper & Utility Functions
 * Sistem Informasi Monitoring Parkir Bandara Malikussaleh
 */

require_once __DIR__ . '/../config/app.php';

/**
 * Escape string untuk pencegahan XSS
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Menyimpan Flash Message di Session
 */
function set_flash($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message
    ];
}

/**
 * Menampilkan Flash Message jika ada
 */
function render_flash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        $icon = 'fa-circle-info';
        if ($msg['type'] === 'success') $icon = 'fa-circle-check';
        if ($msg['type'] === 'danger') $icon = 'fa-circle-xmark';
        if ($msg['type'] === 'warning') $icon = 'fa-triangle-exclamation';

        echo "
        <div class='alert alert-{$msg['type']} alert-dismissible fade show d-flex align-items-center shadow-sm border-0 mb-4' role='alert'>
            <i class='fa-solid {$icon} me-2 fs-5'></i>
            <div>{$msg['text']}</div>
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

/**
 * Format tanggal Indonesia: 30 September 2026
 */
function format_tanggal_indo($date_str) {
    if (!$date_str) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $timestamp = strtotime($date_str);
    $d = date('j', $timestamp);
    $m = (int)date('n', $timestamp);
    $y = date('Y', $timestamp);
    return "{$d} " . ($bulan[$m] ?? '') . " {$y}";
}

/**
 * Format waktu: 08:30 WIB
 */
function format_waktu($time_str) {
    if (!$time_str) return '-';
    return date('H:i', strtotime($time_str)) . ' WIB';
}

/**
 * Format durasi dari menit ke jam dan menit
 */
function format_durasi($total_menit) {
    if ($total_menit === null || $total_menit < 0) return '-';
    $jam = floor($total_menit / 60);
    $sisa_menit = $total_menit % 60;

    if ($jam > 0 && $sisa_menit > 0) {
        return "{$jam} Jam {$sisa_menit} Menit";
    } elseif ($jam > 0) {
        return "{$jam} Jam";
    } else {
        return "{$sisa_menit} Menit";
    }
}

/**
 * Badge status HTML
 */
function badge_status($status) {
    if ($status === 'masih_parkir') {
        return '<span class="badge-status-parkir status-masih-parkir"><i class="fa-solid fa-clock"></i> Masih Parkir</span>';
    } elseif ($status === 'sudah_keluar') {
        return '<span class="badge-status-parkir status-sudah-keluar"><i class="fa-solid fa-circle-check"></i> Sudah Keluar</span>';
    }
    return '<span class="badge bg-secondary">' . e($status) . '</span>';
}

/**
 * Menghasilkan kode transaksi otomatis berformat: MLK-YYYYMMDD-XXXX
 */
function generate_kode_transaksi($pdo) {
    $today = date('Ymd');
    $prefix = "MLK-{$today}-";

    $stmt = $pdo->prepare("SELECT kode_transaksi FROM parkir WHERE kode_transaksi LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $last_num = (int)substr($last, -4);
        $next_num = $last_num + 1;
    } else {
        $next_num = 1;
    }

    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Menghasilkan nomor karcis urutan harian: K-001, K-002
 */
function generate_nomor_karcis($pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parkir WHERE tanggal = ?");
    $stmt->execute([$today]);
    $count = (int)$stmt->fetchColumn();
    $next_num = $count + 1;
    return 'K-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Catat aktivitas pengguna ke audit log
 */
function log_aktivitas($pdo, $aktivitas, $tipe = 'sistem') {
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

        $stmt = $pdo->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, tipe, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $aktivitas, $tipe, $ip, $ua]);
    } catch (Exception $e) {
        // Abaikan kegagalan logging agar tidak mengganggu flow utama
    }
}
