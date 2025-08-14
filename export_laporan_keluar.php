<?php
require_once __DIR__ . '/security_bootstrap.php';
require_auth();
include 'koneksi.php';

function format_tanggal_indo_laporan($tanggal)
{
    if (empty($tanggal)) return '';
    $timestamp = strtotime($tanggal);
    return date('d/m/Y', $timestamp);
}

$filter_date = $_GET['filter_date'] ?? null;          // satu tanggal
$start_date  = $_GET['start_date'] ?? null;            // rentang tanggal
$end_date    = $_GET['end_date'] ?? null;
$status      = $_GET['status_filter'] ?? '';           // status
$search_q    = $_GET['s'] ?? '';                       // nama/kode
$doc_q       = $_GET['doc'] ?? '';                     // dokumen
$batch_q     = $_GET['batch'] ?? '';                   // batch
$desc_q      = $_GET['ket'] ?? '';                     // keterangan

$sql = "
    SELECT
        t.transaction_date,
        p.product_name,
        p.sku,
        t.quantity_kg,
        t.quantity_sacks,
        t.document_number,
        t.batch_number,
        t.description,
        t.status
    FROM
        outgoing_transactions t
    JOIN
        products p ON t.product_id = p.id
    WHERE 1=1
";

$params = [];
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND t.transaction_date BETWEEN :start AND :end";
    $params[':start'] = $start_date;
    $params[':end'] = $end_date;
} elseif (!empty($filter_date)) {
    $sql .= " AND t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}

if (!empty($status)) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $status;
}
if (!empty($search_q)) {
    $sql .= " AND (p.product_name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = '%' . $search_q . '%';
}
if (!empty($doc_q)) {
    $sql .= " AND t.document_number LIKE :doc";
    $params[':doc'] = '%' . $doc_q . '%';
}
if (!empty($batch_q)) {
    $sql .= " AND t.batch_number LIKE :batch";
    $params[':batch'] = '%' . $batch_q . '%';
}
if (!empty($desc_q)) {
    $sql .= " AND t.description LIKE :desc";
    $params[':desc'] = '%' . $desc_q . '%';
}

// Hilangkan LIMIT dan OFFSET untuk mengambil semua data berdasarkan filter
$sql .= " ORDER BY t.document_number, t.description, t.transaction_date, t.created_at";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($start_date) && !empty($end_date)) {
    $filename = "laporan_barang_keluar_{$start_date}_sd_{$end_date}.csv";
} elseif (!empty($filter_date)) {
    $filename = "laporan_barang_keluar_{$filter_date}.csv";
} else {
    $filename = "laporan_barang_keluar_semua.csv";
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

$header = ['Nama Barang', 'Kode Barang', 'Qty (Sak)', 'Qty (Kg)', 'Keterangan'];
fputcsv($output, $header, ';', '"'); // Gunakan semicolon sebagai delimiter dan quote untuk escape

function cleanCsvValue($value) {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value); // Hapus newline dan tab
    $value = trim($value);
    // Escape koma dan tanda kutip agar tidak memisah kolom
    $value = str_replace('"', '""', $value); // Escape tanda kutip ganda
    return $value;
}

foreach ($results as $row) {
    $csv_row = [
        cleanCsvValue($row['product_name'] ?? ''),
        cleanCsvValue($row['sku'] ?? ''),
        cleanCsvValue(formatAngka($row['quantity_sacks'] ?? 0)),
        cleanCsvValue(formatAngka($row['quantity_kg'] ?? 0)),
        cleanCsvValue($row['description'] ?? '')
    ];
    fputcsv($output, $csv_row, ';', '"'); // Gunakan semicolon sebagai delimiter dan quote untuk escape
}

fclose($output);
exit();
?>
