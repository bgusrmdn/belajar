<?php
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_qty_kg = $_GET['filter_qty_kg'] ?? '';
$hide_zero_closing = isset($_GET['hide_zero_closing']) ? (bool)$_GET['hide_zero_closing'] : false;

// Hitung tanggal sebelumnya (H-1) untuk mengambil data barang masuk/keluar dan stock awal
$previous_date = date('Y-m-d', strtotime($filter_date . ' -1 day'));

// QUERY FINAL DENGAN LOGIKA BARU:
// - Stock awal tanggal X = stock awal tanggal X-1 (transaksi sebelum tanggal X-1)
// - Barang masuk/keluar = transaksi pada tanggal X-1
$sql = "
    SELECT
        p.id, p.sku, p.product_name,
        
        (SUM(CASE WHEN t.type = 'IN' AND t.transaction_date < ? THEN t.quantity_kg ELSE 0 END) -
         SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date < ? THEN t.quantity_kg ELSE 0 END))
        AS opening_stock_kg,
        
        (SUM(CASE WHEN t.type = 'IN' AND t.transaction_date < ? THEN t.quantity_sacks ELSE 0 END) -
         SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date < ? THEN t.quantity_sacks ELSE 0 END))
        AS opening_stock_sak,

        SUM(CASE WHEN t.type = 'IN' AND t.transaction_date = ? THEN t.quantity_kg ELSE 0 END) AS incoming_kg_today,
        SUM(CASE WHEN t.type = 'IN' AND t.transaction_date = ? THEN t.quantity_sacks ELSE 0 END) AS incoming_sak_today,
        
        SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date = ? THEN t.quantity_kg ELSE 0 END) AS outgoing_kg_today,
        SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date = ? THEN t.quantity_sacks ELSE 0 END) AS outgoing_sak_today

    FROM
        products p
    LEFT JOIN (
        SELECT product_id, transaction_date, quantity_kg, quantity_sacks, 'IN' as type FROM incoming_transactions
        UNION ALL
        SELECT product_id, transaction_date, quantity_kg, quantity_sacks, 'OUT' as type FROM outgoing_transactions
    ) as t ON p.id = t.product_id
    GROUP BY
        p.id, p.sku, p.product_name
    ORDER BY
        p.product_name ASC
";

$stmt = $pdo->prepare($sql);

// Parameter dengan logika baru:
// - Stock awal menggunakan $previous_date (untuk menghitung stock awal tanggal X-1)
// - Barang masuk/keluar menggunakan $previous_date (transaksi pada H-1)
$params = [
    $previous_date,  // untuk opening_stock_kg (IN) - transaksi sebelum tanggal X-1
    $previous_date,  // untuk opening_stock_kg (OUT) - transaksi sebelum tanggal X-1
    $previous_date,  // untuk opening_stock_sak (IN) - transaksi sebelum tanggal X-1
    $previous_date,  // untuk opening_stock_sak (OUT) - transaksi sebelum tanggal X-1
    $previous_date,  // untuk incoming_kg_today - transaksi pada H-1
    $previous_date,  // untuk incoming_sak_today - transaksi pada H-1
    $previous_date,  // untuk outgoing_kg_today - transaksi pada H-1
    $previous_date,  // untuk outgoing_sak_today - transaksi pada H-1
];

$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report_data = [];
foreach ($results as $row) {
    $closing_stock_kg = $row['opening_stock_kg'] + $row['incoming_kg_today'] - $row['outgoing_kg_today'];

    if (is_numeric($filter_qty_kg) && $closing_stock_kg < (float)$filter_qty_kg) {
        continue;
    }
    if ($hide_zero_closing && $closing_stock_kg <= 0) {
        continue;
    }

    $closing_stock_sak = $row['opening_stock_sak'] + $row['incoming_sak_today'] - $row['outgoing_sak_today'];
    $average_qty = ($closing_stock_sak != 0) ? $closing_stock_kg / $closing_stock_sak : 0;

    $report_data[] = [
        'sku' => $row['sku'],
        'product_name' => $row['product_name'],
        'opening_stock_kg' => $row['opening_stock_kg'],
        'opening_stock_sak' => $row['opening_stock_sak'],
        'incoming_kg_today' => $row['incoming_kg_today'],
        'incoming_sak_today' => $row['incoming_sak_today'],
        'outgoing_kg_today' => $row['outgoing_kg_today'],
        'outgoing_sak_today' => $row['outgoing_sak_today'],
        'closing_stock_kg' => $closing_stock_kg,
        'closing_stock_sak' => $closing_stock_sak,
        'average_qty' => $average_qty,
    ];
}
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-3 fw-bold">Laporan Stok Harian</h2>
            <form action="index.php" method="GET" class="filter-form">
                <input type="hidden" name="page" value="laporan">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label fw-semibold small">Pilih Tanggal</label><input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold small">Stok Akhir (Kg) >=</label><input type="number" step="any" name="filter_qty_kg" class="form-control" placeholder="Contoh: 100" value="<?= htmlspecialchars($filter_qty_kg) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold small">&nbsp;</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="hideZeroClosing" name="hide_zero_closing" <?= $hide_zero_closing ? 'checked' : '' ?>>
                            <label class="form-check-label" for="hideZeroClosing">
                                Sembunyikan Stok Akhir = 0
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button>
                        <a href="export_laporan_harian.php?<?= http_build_query($_GET) ?>" class="btn btn-success" title="Export"><i class="bi bi-file-earmark-spreadsheet-fill"></i></a>
                        <a href="print_laporan_harian.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Laporan">
                            <i class="bi bi-printer-fill"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover laporan-table">
                    <thead>
                        <tr class="table-primary">
                            <th rowspan="2" class="text-center align-middle border-dark" style="min-width: 50px;">No</th>
                            <th rowspan="2" class="text-center align-middle border-dark" style="min-width: 120px;">Kode Barang</th>
                            <th rowspan="2" class="text-start align-middle border-dark" style="min-width: 200px;">Nama Barang</th>
                            <th colspan="2" class="text-center border-dark">Stok Awal</th>
                            <th colspan="2" class="text-center border-dark">Barang Masuk</th>
                            <th colspan="2" class="text-center border-dark">Barang Keluar</th>
                            <th colspan="2" class="text-center border-dark">Stok Akhir</th>
                            <th rowspan="2" class="text-center align-middle border-dark" style="min-width: 100px;">Rata-rata Qty</th>
                        </tr>
                        <tr class="sub-header">
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Kg</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Sak</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Kg</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Sak</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Kg</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Sak</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Kg</th>
                            <th class="text-center border-dark fw-bold text-white" style="min-width: 80px;">Sak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted p-4">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                    Tidak ada data untuk ditampilkan.
                                </td>
                            </tr>
                        <?php else: 
                            $nomor = 1;
                            foreach ($report_data as $data): ?>
                                <tr data-closingkg="<?= (float)$data['closing_stock_kg'] ?>">
                                    <td class="text-center border fw-bold text-dark"><?= $nomor++ ?></td>
                                    <td class="text-center border fw-semibold text-dark"><?= htmlspecialchars($data['sku']) ?></td>
                                    <td class="text-start border fw-semibold text-dark"><?= htmlspecialchars($data['product_name']) ?></td>
                                    <td class="text-center align-middle border text-dark"><?= formatAngka($data['opening_stock_kg']) ?></td>
                                    <td class="text-center align-middle border text-dark"><?= formatAngka($data['opening_stock_sak']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #2e7d32;"><?= formatAngka($data['incoming_kg_today']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #2e7d32;"><?= formatAngka($data['incoming_sak_today']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #d32f2f;"><?= formatAngka($data['outgoing_kg_today']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #d32f2f;"><?= formatAngka($data['outgoing_sak_today']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #1976d2;"><?= formatAngka($data['closing_stock_kg']) ?></td>
                                    <td class="text-center align-middle border fw-bold" style="color: #1976d2;"><?= formatAngka($data['closing_stock_sak']) ?></td>
                                    <td class="text-center align-middle border text-dark"><?= formatAngka($data['average_qty']) ?></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('hideZeroClosing');
    const tbody = document.querySelector('.laporan-table tbody');
    if (!checkbox || !tbody) return;
    function applyHide() {
        const hide = checkbox.checked;
        tbody.querySelectorAll('tr').forEach(tr => {
            // Skip "no data" row
            const firstCell = tr.querySelector('td');
            if (firstCell && firstCell.hasAttribute('colspan')) return;
            const val = parseFloat(tr.dataset.closingkg || '0');
            tr.style.display = (hide && val <= 0) ? 'none' : '';
        });
    }
    checkbox.addEventListener('change', applyHide);
    applyHide();
});
</script>

<style>
.laporan-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 0.9rem;
}

.laporan-table th,
.laporan-table td {
    border: 2px solid #dee2e6 !important;
    padding: 8px 12px;
    vertical-align: middle;
}

.laporan-table thead th {
    border: 2px solid #495057 !important;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.laporan-table .sub-header th {
    border-top: 1px solid #495057 !important;
    font-size: 0.75rem;
    padding: 6px 8px;
    color: white !important;
}

.laporan-table tbody tr:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease;
}

.laporan-table tbody tr:nth-child(even) {
    background-color: #fafafa;
}

.laporan-table .table-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white !important;
}

.laporan-table .table-primary th {
    color: white !important;
    border-color: #004085 !important;
}

/* Memastikan teks tetap terlihat jelas */
.laporan-table th,
.laporan-table td {
    color: #212529 !important;
}

/* Khusus untuk sub-header, pastikan tetap putih */
.laporan-table .sub-header th {
    color: white !important;
}

.laporan-table tbody tr:hover td {
    background-color: #e9ecef !important;
    color: #212529 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .laporan-table {
        font-size: 0.8rem;
    }
    
    .laporan-table th,
    .laporan-table td {
        padding: 6px 8px;
    }
    
    .laporan-table thead th {
        font-size: 0.7rem;
    }
    
    .laporan-table .sub-header th {
        font-size: 0.65rem;
        padding: 4px 6px;
    }
}
</style>