<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");

$host       = 'localhost';
$dbname     = 'db_toko_online';
$username   = 'root'; 
$password   = '';     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? '';
$id = $_GET['id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

switch ($resource) {
    
    // ==========================================
    // 1. CRUD DATA MASTER (PRODUK)
    // ==========================================
    case 'produk':
        if ($method === 'GET') {
            if ($id) {
                // Select 1
                $stmt = $pdo->prepare("SELECT * FROM produk WHERE id_produk = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                if(!$data) {
                    echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                } else {
                    echo json_encode(["status" => "success", "data" => $data]);
                }
            } else {
                // Select All
                $stmt = $pdo->query("SELECT * FROM produk");
                echo json_encode(["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        } 
        elseif ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO produk (nama_produk, id_kategori, harga, stok) VALUES (?, ?, ?, ?)");
            $stmt->execute([$input['nama_produk'], $input['id_kategori'], $input['harga'], $input['stok']]);
            echo json_encode(["status" => "success", "message" => "Produk berhasil ditambahkan"]);
        } 
        elseif ($method === 'PUT') {
            // Validasi Update
            $cek = $pdo->prepare("SELECT COUNT(*) FROM produk WHERE id_produk = ?");
            $cek->execute([$id]);
            if ($cek->fetchColumn() == 0) {
                echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                break;
            }

            $stmt = $pdo->prepare("UPDATE produk SET nama_produk = ?, id_kategori = ?, harga = ?, stok = ? WHERE id_produk = ?");
            $stmt->execute([$input['nama_produk'], $input['id_kategori'], $input['harga'], $input['stok'], $id]);
            echo json_encode(["status" => "success", "message" => "Produk berhasil diupdate"]);
        } 
        elseif ($method === 'DELETE') {
            // Validasi Delete
            $cek = $pdo->prepare("SELECT COUNT(*) FROM produk WHERE id_produk = ?");
            $cek->execute([$id]);
            if ($cek->fetchColumn() == 0) {
                echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM produk WHERE id_produk = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Produk berhasil dihapus"]);
        }
        break;

    // ==========================================
    // 2. CRUD DATA TRANSAKSIONAL (PESANAN)
    // ==========================================
    case 'transaksi':
        if ($method === 'GET') {
            if ($id) {
                // Select 1 (Berdasarkan ID Transaksi)
                $stmt = $pdo->prepare("SELECT * FROM pesanan WHERE id_pesanan = ?");
                $stmt->execute([$id]);
                $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(!$pesanan) {
                    echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                } else {
                    // Ambil detailnya juga
                    $stmt_dtl = $pdo->prepare("SELECT * FROM detail_pesanan WHERE id_pesanan = ?");
                    $stmt_dtl->execute([$id]);
                    $pesanan['detail'] = $stmt_dtl->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode(["status" => "success", "data" => $pesanan]);
                }
            } else {
                // Select All
                $stmt = $pdo->query("SELECT * FROM pesanan");
                echo json_encode(["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
        } 
        elseif ($method === 'POST') {
            // Insert Transaksi Induk
            $stmt = $pdo->prepare("INSERT INTO pesanan (id_pelanggan, tanggal_pesanan, total_harga) VALUES (?, NOW(), 0)");
            $stmt->execute([$input['id_pelanggan']]);
            echo json_encode(["status" => "success", "message" => "Transaksi berhasil dibuat", "id_pesanan" => $pdo->lastInsertId()]);
        } 
        elseif ($method === 'DELETE') {
            // Delete Transaksi beserta detailnya
            $cek = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_pesanan = ?");
            $cek->execute([$id]);
            if ($cek->fetchColumn() == 0) {
                echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM pesanan WHERE id_pesanan = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Transaksi berhasil dihapus"]);
        }
        break;

    // ==========================================
    // 3. CRUD DETAIL TRANSAKSI
    // ==========================================
    case 'transaksi_detail':
        if ($method === 'POST') {
            // Insert Detail Transaksi berdasarkan ID Transaksi
            $stmt_pr = $pdo->prepare("SELECT harga FROM produk WHERE id_produk = ?");
            $stmt_pr->execute([$input['id_produk']]);
            $harga_satuan = $stmt_pr->fetchColumn();
            
            $subtotal = $harga_satuan * $input['jumlah'];

            $stmt = $pdo->prepare("INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$input['id_pesanan'], $input['id_produk'], $input['jumlah'], $harga_satuan, $subtotal]);
            
            // Update total harga
            $pdo->query("UPDATE pesanan SET total_harga = total_harga + $subtotal WHERE id_pesanan = " . $input['id_pesanan']);
            
            echo json_encode(["status" => "success", "message" => "Detail transaksi berhasil ditambahkan"]);
        }
        elseif ($method === 'DELETE') {
            // Validasi data ada atau tidak
            $cek = $pdo->prepare("SELECT id_pesanan, subtotal FROM detail_pesanan WHERE id_detail = ?");
            $cek->execute([$id]);
            $detail = $cek->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                echo json_encode(["status" => "error", "message" => "Data tidak ada mau ngapain dah?"]);
                break;
            }
            
            // Kurangi total harga di pesanan induk
            $pdo->query("UPDATE pesanan SET total_harga = total_harga - {$detail['subtotal']} WHERE id_pesanan = {$detail['id_pesanan']}");
            
            $stmt = $pdo->prepare("DELETE FROM detail_pesanan WHERE id_detail = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Detail transaksi berhasil dihapus"]);
        }
        break;

    // ==========================================
    // 4. STATISTIC DATA TRANSAKSIONAL (Per Bulan/Tahun)
    // ==========================================
    case 'stats':
        if ($method === 'GET') {
            $bulan = $_GET['bulan'] ?? date('m'); // Default bulan ini
            $tahun = $_GET['tahun'] ?? date('Y'); // Default tahun ini

            $stmt = $pdo->prepare("SELECT COUNT(*) as jumlah_transaksi, SUM(total_harga) as total_pendapatan 
                                   FROM pesanan 
                                   WHERE MONTH(tanggal_pesanan) = ? AND YEAR(tanggal_pesanan) = ?");
            $stmt->execute([$bulan, $tahun]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                "status" => "success", 
                "filter" => "Bulan: $bulan, Tahun: $tahun",
                "data" => [
                    "jumlah_transaksi" => (int)$stats['jumlah_transaksi'],
                    "total_pendapatan" => (float)$stats['total_pendapatan']
                ]
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint tidak valid!"]);
        break;
}
?>