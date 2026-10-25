<?php
/**
 * Master Data Jenis Kendaraan
 * Bandara Malikussaleh (LSW)
 */
$page_title = 'Master Jenis Kendaraan';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';

check_role(['admin']);

$pdo = getDB();

// 1. Tambah Jenis Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_jenis'])) {
    $nama = trim($_POST['nama_jenis'] ?? '');
    $kode = strtoupper(trim($_POST['kode_kategori'] ?? ''));
    $icon = trim($_POST['icon_class'] ?? 'fa-solid fa-car');
    $urutan = (int)($_POST['urutan'] ?? 1);

    if (empty($nama) || empty($kode)) {
        set_flash('danger', 'Nama jenis dan kode kategori wajib diisi.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO jenis_kendaraan (nama_jenis, kode_kategori, icon_class, urutan, status) VALUES (?, ?, ?, ?, 'aktif')");
            $stmt->execute([$nama, $kode, $icon, $urutan]);

            log_aktivitas($pdo, "Menambahkan jenis kendaraan baru: {$nama} ({$kode})", 'master');
            set_flash('success', "Jenis kendaraan <strong>" . e($nama) . "</strong> berhasil ditambahkan.");
            header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menambahkan jenis kendaraan: ' . $e->getMessage());
        }
    }
}

// 2. Edit Jenis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_jenis'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nama = trim($_POST['nama_jenis'] ?? '');
    $kode = strtoupper(trim($_POST['kode_kategori'] ?? ''));
    $icon = trim($_POST['icon_class'] ?? 'fa-solid fa-car');
    $urutan = (int)($_POST['urutan'] ?? 1);
    $status = trim($_POST['status'] ?? 'aktif');

    if ($id <= 0 || empty($nama)) {
        set_flash('danger', 'Data tidak valid.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE jenis_kendaraan SET nama_jenis = ?, kode_kategori = ?, icon_class = ?, urutan = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama, $kode, $icon, $urutan, $status, $id]);

            log_aktivitas($pdo, "Memperbarui jenis kendaraan ID: {$id} ({$nama})", 'master');
            set_flash('success', "Jenis kendaraan <strong>" . e($nama) . "</strong> berhasil diperbarui.");
            header('Location: ' . BASE_URL . '/admin/jenis-kendaraan.php');
            exit;
        } catch (Exception $e) {
            set_flash('danger', 'Gagal memperbarui jenis kendaraan: ' . $e->getMessage());
        }
    }
}

// Ambil daftar jenis kendaraan beserta jumlah transaksi
$stmt_list = $pdo->query("
    SELECT j.*, COUNT(p.id) as total_transaksi
    FROM jenis_kendaraan j
    LEFT JOIN parkir p ON j.id = p.jenis_kendaraan_id
    GROUP BY j.id
    ORDER BY j.urutan ASC
");
$jenis_list = $stmt_list->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header-box">
    <div>
        <h1 class="page-header-title">
            <i class="fa-solid fa-car-tunnel text-primary me-2"></i> Master Data Jenis Kendaraan
        </h1>
        <div class="page-header-subtitle">
            Klasifikasi jenis kendaraan yang diizinkan memasuki area parkir Bandara Malikussaleh
        </div>
    </div>
    <button type="button" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;" data-bs-toggle="modal" data-bs-target="#modalTambahJenis">
        <i class="fa-solid fa-plus-circle me-1 text-warning"></i> Tambah Jenis Kendaraan
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover table-kemenhub align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 70px;">Urutan</th>
                    <th>Icon</th>
                    <th>Nama Jenis Kendaraan</th>
                    <th>Kode Kategori</th>
                    <th>Total Transaksi Terdata</th>
                    <th>Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jenis_list as $j): ?>
                    <tr>
                        <td class="text-center fw-bold"><?= $j['urutan'] ?></td>
                        <td>
                            <div class="bg-light rounded p-2 text-center d-inline-block border" style="width: 42px;">
                                <i class="<?= e($j['icon_class']) ?> fa-lg text-primary"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= e($j['nama_jenis']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-secondary font-monospace"><?= e($j['kode_kategori']) ?></span>
                        </td>
                        <td>
                            <span class="fw-semibold text-primary"><?= number_format($j['total_transaksi']) ?> Transaksi</span>
                        </td>
                        <td>
                            <?php if ($j['status'] === 'aktif'): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit-jenis"
                                data-id="<?= $j['id'] ?>"
                                data-nama="<?= e($j['nama_jenis']) ?>"
                                data-kode="<?= e($j['kode_kategori']) ?>"
                                data-icon="<?= e($j['icon_class']) ?>"
                                data-urutan="<?= $j['urutan'] ?>"
                                data-status="<?= e($j['status']) ?>"
                                title="Edit Jenis">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Jenis -->
<div class="modal fade" id="modalTambahJenis" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle text-warning me-2"></i> Tambah Jenis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Jenis Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_jenis" class="form-control" placeholder="Contoh: Sepeda Listrik / VIP" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Kode Kategori <span class="text-danger">*</span></label>
                            <input type="text" name="kode_kategori" class="form-control text-uppercase" placeholder="Contoh: VIP" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">No. Urut Tampil</label>
                            <input type="number" name="urutan" class="form-control" value="<?= count($jenis_list) + 1 ?>" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Icon FontAwesome</label>
                        <input type="text" name="icon_class" class="form-control" value="fa-solid fa-car">
                        <small class="text-muted">Contoh: <code>fa-solid fa-motorcycle</code>, <code>fa-solid fa-car-side</code>, <code>fa-solid fa-bus</code></small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_jenis" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        Simpan Jenis
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Jenis -->
<div class="modal fade" id="modalEditJenis" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white" style="background: #0b2e59 !important; border-bottom: 3px solid #f59e0b;">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-warning me-2"></i> Edit Jenis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="id" id="edit_j_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Jenis Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" name="nama_jenis" id="edit_j_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Kode Kategori</label>
                            <input type="text" name="kode_kategori" id="edit_j_kode" class="form-control text-uppercase" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Urutan</label>
                            <input type="number" name="urutan" id="edit_j_urutan" class="form-control" min="1">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label small fw-bold">Icon FontAwesome</label>
                            <input type="text" name="icon_class" id="edit_j_icon" class="form-control">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" id="edit_j_status" class="form-select">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="edit_jenis" value="1" class="btn btn-primary" style="background: #0b2e59; border-color: #0b2e59;">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const modalEditJ = new bootstrap.Modal(document.getElementById('modalEditJenis'));
    document.querySelectorAll('.btn-edit-jenis').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_j_id').value = this.getAttribute('data-id');
            document.getElementById('edit_j_nama').value = this.getAttribute('data-nama');
            document.getElementById('edit_j_kode').value = this.getAttribute('data-kode');
            document.getElementById('edit_j_icon').value = this.getAttribute('data-icon');
            document.getElementById('edit_j_urutan').value = this.getAttribute('data-urutan');
            document.getElementById('edit_j_status').value = this.getAttribute('data-status');
            modalEditJ.show();
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
