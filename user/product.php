<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Get product ID from query parameter
$productId = isset($_GET['id']) ? (int) $_GET['id'] : null;

if (!$productId) {
    http_response_code(400);
    echo json_encode(["error" => "Product ID is required"]);
    exit;
}

try {
    // Fetch product (only active products)
    $stmt = $pdo->prepare("
        SELECT id, name, category, followers, quantity, price, tutorial_link, status, created_at, updated_at
        FROM products
        WHERE id = :id AND status = 'Active'
    ");
    $stmt->execute([':id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(["error" => "Product not found"]);
        exit;
    }

    // Get active accounts count (no sensitive info)
    $stmtAccounts = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM product_accounts
        WHERE product_id = :product_id AND status = 'Active'
    ");
    $stmtAccounts->execute([':product_id' => $productId]);
    $accountInfo = $stmtAccounts->fetch(PDO::FETCH_ASSOC);
    
    $product['available_accounts'] = (int) $accountInfo['count'];

    echo json_encode([
        "success" => true,
        "product" => $product
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
?>

