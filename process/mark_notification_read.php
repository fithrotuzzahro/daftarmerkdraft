<?php
session_start();
require_once 'config_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['NIK_NIP'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id_notif = $data['id_notif'] ?? null;
$nik_nip = $_SESSION['NIK_NIP'];

if (!$id_notif) {
    echo json_encode(['success' => false, 'message' => 'ID notifikasi tidak valid']);
    exit;
}

try {
    // Update status is_read menjadi 1, pastikan notifikasi milik user yang login
    $query = "UPDATE notifikasi SET is_read = 1 WHERE id_notif = :id_notif AND NIK_NIP = :nik_nip";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        'id_notif' => $id_notif,
        'nik_nip' => $nik_nip
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notifikasi ditandai sebagai dibaca']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notifikasi tidak ditemukan atau sudah dibaca']);
    }
    
} catch (PDOException $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat update notifikasi']);
}
?>