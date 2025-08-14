<?php
require_once __DIR__ . '/security_bootstrap.php';
require_auth();
include 'koneksi.php';

$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_qty_kg = $_GET['filter_qty_kg'] ?? '';

$previous_date = date('Y-m-d', strtotime($filter_date . ' -1 day'));

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

$filename = "laporan_harian_" . $filter_date . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

function formatAngkaCSV($angka) {
    if ($angka === null || $angka === '') {
        return '0';
    }
    $nomor = (float)$angka;
    return number_format($nomor, 2, '.', ''); // Gunakan titik untuk desimal, tanpa separator ribuan
}

fputcsv($output, ['LAPORAN HARIAN STOCK'], ';');
fputcsv($output, ['Tanggal:', date('d/m/Y', strtotime($filter_date))], ';');
fputcsv($output, ['Waktu Export:', date('d/m/Y H:i:s')], ';');
if (!empty($filter_qty_kg)) {
    fputcsv($output, ['Filter Qty Kg:', '>= ' . number_format($filter_qty_kg, 2, '.', '')], ';');
}
fputcsv($output, ['DETAIL DATA:'], ';');

$header = ['No', 'Kode Barang', 'Nama Barang', 'Stok Awal (Kg)', 'Stok Awal (Sak)', 'Masuk (Kg)', 'Masuk (Sak)', 'Keluar (Kg)', 'Keluar (Sak)', 'Stok Akhir (Kg)', 'Stok Akhir (Sak)', 'Rata-rata Qty'];
fputcsv($output, $header, ';'); // Gunakan semicolon sebagai separator

$nomor = 1;
foreach ($results as $row) {
    $closing_stock_kg = $row['opening_stock_kg'] + $row['incoming_kg_today'] - $row['outgoing_kg_today'];

    if (is_numeric($filter_qty_kg) && $closing_stock_kg < (float)$filter_qty_kg) {
        continue;
    }

    $closing_stock_sak = $row['opening_stock_sak'] + $row['incoming_sak_today'] - $row['outgoing_sak_today'];
    $average_qty = ($closing_stock_sak != 0) ? $closing_stock_kg / $closing_stock_sak : 0;

    $csv_row = [
        $nomor++,
        $row['sku'] ?? '',
        $row['product_name'] ?? '',
        formatAngkaCSV($row['opening_stock_kg'] ?? 0),
        formatAngkaCSV($row['opening_stock_sak'] ?? 0),
        formatAngkaCSV($row['incoming_kg_today'] ?? 0),
        formatAngkaCSV($row['incoming_sak_today'] ?? 0),
        formatAngkaCSV($row['outgoing_kg_today'] ?? 0),
        formatAngkaCSV($row['outgoing_sak_today'] ?? 0),
        formatAngkaCSV($closing_stock_kg),
        formatAngkaCSV($closing_stock_sak),
        formatAngkaCSV($average_qty),
    ];
    fputcsv($output, $csv_row, ';'); // Gunakan semicolon sebagai separator
}

fclose($output);
exit();
?>
