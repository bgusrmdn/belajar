<?php
require_once __DIR__ . '/security_bootstrap.php';
require_auth();
include 'koneksi.php';

$filter_date = $_GET['filter_date'] ?? null; // fallback: filter satu tanggal (opsional)
$start_date = $_GET['start_date'] ?? null;   // sesuai halaman: rentang tanggal
$end_date   = $_GET['end_date'] ?? null;     // sesuai halaman: rentang tanggal
$filter_status = $_GET['status_filter'] ?? '';
$search_query = $_GET['s'] ?? '';
$po_query = $_GET['po'] ?? '';
$supplier_query = $_GET['sup'] ?? '';
$doc_query = $_GET['doc'] ?? '';
$batch_query = $_GET['batch'] ?? '';

$sql = "SELECT 
            t.po_number,
            t.supplier,
            t.license_plate,
            p.product_name,
            p.sku,
            t.quantity_kg,
            t.quantity_sacks,
            t.document_number,
            t.lot_number
        FROM incoming_transactions t
        JOIN products p ON t.product_id = p.id
        WHERE 1=1";

$params = [];
if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND t.transaction_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date']   = $end_date;
} elseif (!empty($filter_date)) {
    $sql .= " AND t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}
if (!empty($filter_status)) {
    $sql .= " AND t.status = :status_filter";
    $params[':status_filter'] = $filter_status;
}
if (!empty($search_query)) {
    $sql .= " AND (p.product_name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}
if (!empty($po_query)) {
    $sql .= " AND t.po_number LIKE :po_number";
    $params[':po_number'] = '%' . $po_query . '%';
}
if (!empty($supplier_query)) {
    $sql .= " AND t.supplier LIKE :supplier_query";
    $params[':supplier_query'] = '%' . $supplier_query . '%';
}
if (!empty($doc_query)) {
    $sql .= " AND t.document_number LIKE :document_number";
    $params[':document_number'] = '%' . $doc_query . '%';
}
if (!empty($batch_query)) {
    $sql .= " AND t.batch_number LIKE :batch_number";
    $params[':batch_number'] = '%' . $batch_query . '%';
}

$sql .= " ORDER BY t.document_number, t.po_number, t.created_at";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($start_date) && !empty($end_date)) {
    $filename = "laporan_barang_masuk_{$start_date}_sd_{$end_date}.csv";
} elseif (!empty($filter_date)) {
    $filename = "laporan_barang_masuk_{$filter_date}.csv";
} else {
    $filename = "laporan_barang_masuk.csv";
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

$header = ['Nomor PO', 'Supplier', 'Nomor Kendaraan', 'Nama Barang', 'Kode Barang', 'Qty (Sak)', 'Qty (Kg)', 'Nomor Dokumen', '501'];
fputcsv($output, $header, ';', '"'); // Gunakan semicolon sebagai delimiter dan quote untuk escape

function cleanCsvValue($value) {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value); // Hapus newline dan tab
    $value = trim($value);
    // Escape koma dan tanda kutip agar tidak memisah kolom
    $value = str_replace('"', '""', $value); // Escape tanda kutip ganda
    return $value;
}
foreach ($transactions as $row) {
    $csv_row = [
        cleanCsvValue($row['po_number'] ?? ''),
        cleanCsvValue($row['supplier'] ?? ''),
        cleanCsvValue($row['license_plate'] ?? ''),
        cleanCsvValue($row['product_name'] ?? ''),
        cleanCsvValue($row['sku'] ?? ''),
        cleanCsvValue(formatAngka($row['quantity_sacks'] ?? 0)),
        cleanCsvValue(formatAngka($row['quantity_kg'] ?? 0)),
        cleanCsvValue($row['document_number'] ?? ''),
        cleanCsvValue(formatAngka($row['lot_number'] ?? 0))
    ];
    fputcsv($output, $csv_row, ';', '"'); // Gunakan semicolon sebagai delimiter dan quote untuk escape
}

fclose($output);
exit();
?>
