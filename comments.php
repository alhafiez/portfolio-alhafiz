<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
// KONFIGURASI DATABASE — sesuaikan dengan server kamu
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ganti dengan username MySQL kamu
define('DB_PASS', '');           // ganti dengan password MySQL kamu
define('DB_NAME', 'portfolio_db');
// ============================================================

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $conn->connect_error]);
        exit();
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)    return 'Baru saja';
    if ($diff < 3600)  return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', strtotime($timestamp));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// =====================
// GET — Ambil komentar
// =====================
if ($method === 'GET' && $action === 'get') {
    $db = getDB();
    $page  = max(1, intval($_GET['page'] ?? 1));
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total_row = $db->query("SELECT COUNT(*) as total FROM comments WHERE status='approved'")->fetch_assoc();
    $total = $total_row['total'];

    $stmt = $db->prepare(
        "SELECT id, name, message, ai_reply, created_at FROM comments
         WHERE status='approved'
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'message'    => $row['message'],
            'ai_reply'   => $row['ai_reply'],
            'created_at' => timeAgo($row['created_at']),
        ];
    }

    echo json_encode([
        'success'  => true,
        'comments' => $comments,
        'total'    => (int)$total,
        'page'     => $page,
        'pages'    => ceil($total / $limit),
    ]);
    $db->close();
    exit();
}

// =====================
// POST — Kirim komentar
// =====================
if ($method === 'POST' && $action === 'post') {
    $body = json_decode(file_get_contents('php://input'), true);

    $name    = sanitize($body['name']    ?? '');
    $email   = sanitize($body['email']   ?? '');
    $message = sanitize($body['message'] ?? '');
    $ai_reply = sanitize($body['ai_reply'] ?? '');

    if (empty($name) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama dan pesan tidak boleh kosong.']);
        exit();
    }

    if (mb_strlen($name) > 100 || mb_strlen($message) > 2000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Input terlalu panjang.']);
        exit();
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
        exit();
    }

    $db = getDB();

    // Rate limiting sederhana: max 3 komentar per IP per 10 menit
    $ip = $_SERVER['REMOTE_ADDR'];
    $check = $db->prepare(
        "SELECT COUNT(*) as cnt FROM comments
         WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
    );
    $check->bind_param('s', $ip);
    $check->execute();
    $cnt = $check->get_result()->fetch_assoc()['cnt'];
    if ($cnt >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Terlalu banyak komentar. Coba lagi dalam 10 menit.']);
        exit();
    }

    $stmt = $db->prepare(
        "INSERT INTO comments (name, email, message, ai_reply, ip_address, status)
         VALUES (?, ?, ?, ?, ?, 'approved')"
    );
    $stmt->bind_param('sssss', $name, $email, $message, $ai_reply, $ip);

    if ($stmt->execute()) {
        $new_id = $db->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Komentar berhasil dikirim!',
            'comment' => [
                'id'         => $new_id,
                'name'       => $name,
                'message'    => $message,
                'ai_reply'   => $ai_reply,
                'created_at' => 'Baru saja',
            ],
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan komentar.']);
    }

    $db->close();
    exit();
}

// =====================
// Fallback
// =====================
http_response_code(404);
echo json_encode(['success' => false, 'message' => 'Endpoint tidak ditemukan.']);
