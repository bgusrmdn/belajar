<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST EXPORT UNLOADING ===\n";

try {
    require_once 'koneksi.php';
    echo "✅ Koneksi database berhasil\n";
} catch (Exception $e) {
    echo "❌ Koneksi database gagal: " . $e->getMessage() . "\n";
    exit;
}

try {
    $check_table = $pdo->query("SHOW TABLES LIKE 'unloading_records'");
    if ($check_table->rowCount() == 0) {
        echo "❌ Tabel unloading_records tidak ditemukan\n";
        
        echo "🔧 Membuat tabel unloading_records...\n";
        $create_sql = "
        CREATE TABLE `unloading_records` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `incoming_transaction_id` int(11) DEFAULT NULL,
            `tanggal` date NOT NULL,
            `jam_masuk` time DEFAULT NULL,
            `jam_start_qc` time DEFAULT NULL,
            `jam_finish_qc` time DEFAULT NULL,
            `jam_start_bongkar` time DEFAULT NULL,
            `jam_finish_bongkar` time DEFAULT NULL,
            `jam_keluar` time DEFAULT NULL,
            `durasi_bongkar` int(11) DEFAULT NULL,
            `supplier` varchar(255) DEFAULT NULL,
            `nama_barang` varchar(255) DEFAULT NULL,
            `nomor_mobil` varchar(50) DEFAULT NULL,
            `no_mobil` varchar(50) DEFAULT NULL,
            `qty_sak` decimal(10,2) DEFAULT 0,
            `qty_pallet` int(11) DEFAULT 0,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tanggal` (`tanggal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $pdo->exec($create_sql);
        echo "✅ Tabel unloading_records berhasil dibuat\n";
    } else {
        echo "✅ Tabel unloading_records sudah ada\n";
    }
} catch (PDOException $e) {
    echo "❌ Error cek/buat tabel: " . $e->getMessage() . "\n";
    exit;
}

try {
    $count = $pdo->query("SELECT COUNT(*) as total FROM unloading_records")->fetch();
    echo "📊 Total data: {$count['total']} record\n";
    
    if ($count['total'] == 0) {
        echo "🔧 Menambahkan sample data...\n";
        $current_week = date('Y-W');
        $week_parts = explode('-', $current_week);
        $year = $week_parts[0];
        $week = $week_parts[1];
        
        $monday = new DateTime();
        $monday->setISODate($year, $week, 1);
        $monday_str = $monday->format('Y-m-d');
        
        $sample_data = [
            [$monday_str, '08:30:00', '09:00:00', '09:30:00', '10:00:00', '12:00:00', '13:00:00', 120, 'PT Supplier A', 'Beras Premium', 'B 1234 XYZ', 100.50, 5],
            [$monday_str, '14:00:00', '14:30:00', '15:00:00', '15:30:00', '17:00:00', '18:00:00', 90, 'PT Supplier B', 'Gula Pasir', 'B 5678 ABC', 75.25, 3]
        ];
        
        $insert_sql = "INSERT INTO unloading_records (tanggal, jam_masuk, jam_start_qc, jam_finish_qc, jam_start_bongkar, jam_finish_bongkar, jam_keluar, durasi_bongkar, supplier, nama_barang, nomor_mobil, qty_sak, qty_pallet) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        
        foreach ($sample_data as $data) {
            $insert_stmt->execute($data);
        }
        echo "✅ Sample data berhasil ditambahkan\n";
    }
} catch (PDOException $e) {
    echo "❌ Error cek data: " . $e->getMessage() . "\n";
}

echo "\n=== TEST EXPORT ===\n";
try {
    $current_week = date('Y-W');
    $week_parts = explode('-', $current_week);
    $year = intval($week_parts[0]);
    $week = intval($week_parts[1]);
    
    $monday = new DateTime();
    $monday->setISODate($year, $week, 1);
    $saturday = new DateTime();
    $saturday->setISODate($year, $week, 6);
    
    $monday_str = $monday->format('Y-m-d');
    $saturday_str = $saturday->format('Y-m-d');
    
    echo "🗓️ Periode: {$monday_str} - {$saturday_str}\n";
    
    $sql_unloading = "
        SELECT u.*, 
               DATE_FORMAT(u.tanggal, '%d/%m/%Y') as tanggal_formatted,
               DATE_FORMAT(u.tanggal, '%W') as hari_formatted,
               TIME_FORMAT(u.jam_masuk, '%H:%i') as jam_masuk_formatted
        FROM unloading_records u 
        WHERE u.tanggal BETWEEN ? AND ?
        ORDER BY u.tanggal ASC, u.jam_masuk ASC
    ";
    
    $stmt_unloading = $pdo->prepare($sql_unloading);
    $stmt_unloading->execute([$monday_str, $saturday_str]);
    $unloading_records = $stmt_unloading->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Data ditemukan: " . count($unloading_records) . " record\n";
    
    if (!empty($unloading_records)) {
        echo "✅ Export dapat berjalan\n";
        echo "🔗 URL Export: export_unloading.php?week={$current_week}\n";
    } else {
        echo "⚠️ Tidak ada data untuk periode ini\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error test export: " . $e->getMessage() . "\n";
}

echo "\n=== SELESAI ===\n";
?>
