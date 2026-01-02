<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

if (empty($_GET['product_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required parameter: product_id"]);
    exit;
}

$productId = (int) $_GET['product_id'];

try {
    $pdo->beginTransaction();

    $stmtAccounts = $pdo->prepare(
        "DELETE FROM product_accounts WHERE product_id = :product_id"
    );
    $stmtAccounts->execute([':product_id' => $productId]);

    $stmtProduct = $pdo->prepare(
        "DELETE FROM products WHERE id = :id"
    );
    $stmtProduct->execute([':id' => $productId]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Product deleted successfully"
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
}
