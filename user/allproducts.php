<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

// ---------------------------
// Database configuration
// ---------------------------
$host = "localhost";
$db_name = "jsocialogs";
$username = "root";
$password = "";
$charset = "utf8mb4";

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// ---------------------------
// Pagination & Search
// ---------------------------
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

try {
    // Total count for active products
    $countQuery = "SELECT COUNT(*) FROM products WHERE status = 'active' AND (name LIKE :search_name OR category LIKE :search_category)";
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute([
        ':search_name' => "%$search%",
        ':search_category' => "%$search%"
    ]);
    $total = (int) $stmtCount->fetchColumn();

    // Fetch active products
    $stmt = $pdo->prepare("
        SELECT id, name, category, followers, quantity, price, tutorial_link, status, created_at, updated_at
        FROM products
        WHERE status = 'active' AND (name LIKE :search_name OR category LIKE :search_category)
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':search_name', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':search_category', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach only active accounts (no username/password)
    $stmtAccounts = $pdo->prepare("
        SELECT id, status
        FROM product_accounts
        WHERE product_id = :product_id AND status = 'active'
    ");

    foreach ($products as &$product) {
        $stmtAccounts->execute([':product_id' => $product['id']]);
        $accounts = $stmtAccounts->fetchAll(PDO::FETCH_ASSOC);
        $product['accounts'] = $accounts; // only active accounts, no sensitive info
    }

    echo json_encode([
        "success" => true,
        "products" => $products,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "totalPages" => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>