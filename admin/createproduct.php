<?php
// Include database config
header('Content-Type: application/json; charset=utf-8'); 
require_once "../config.php";
require_once "auth.php";

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// Required fields validation
$requiredFields = ['name', 'category', 'accounts', 'price'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

// Ensure accounts is an array
if (!is_array($data['accounts']) || count($data['accounts']) === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Accounts must be a non-empty array of objects with username and password"]);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Insert product
    $stmtProduct = $pdo->prepare("
        INSERT INTO products 
        (name, category, followers, quantity, price, tutorial_link, status) 
        VALUES 
        (:name, :category, :followers, :quantity, :price, :tutorial_link, :status)
    ");

    $stmtProduct->execute([
        ':name' => $data['name'],
        ':category' => $data['category'],
        ':followers' => isset($data['followers']) ? (int) $data['followers'] : 0,
        ':quantity' => 0, // will update after accounts insert
        ':price' => (float) $data['price'],
        ':tutorial_link' => isset($data['tutorialLink']) ? $data['tutorialLink'] : null,
        ':status' => isset($data['status']) ? $data['status'] : 'Active'
    ]);

    $productId = $pdo->lastInsertId();

    // Insert accounts
    $stmtAccount = $pdo->prepare("
        INSERT INTO product_accounts 
        (product_id, username, password, status) 
        VALUES 
        (:product_id, :username, :password, :status)
    ");

    $validAccounts = 0;

    foreach ($data['accounts'] as $account) {
        if (empty($account['username']) || empty($account['password']))
            continue;

        $stmtAccount->execute([
            ':product_id' => $productId,
            ':username' => $account['username'],
            ':password' => encryptPassword($account["password"]),
            ':status' => isset($account['status']) ? $account['status'] : 'Active'
        ]);

        $validAccounts++;
    }

    // Update product quantity
    $stmtUpdate = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
    $stmtUpdate->execute([
        ':quantity' => $validAccounts,
        ':id' => $productId
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Product and accounts saved successfully",
        "product_id" => $productId,
        "accounts_added" => $validAccounts
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "error" => "Database error: " . $e->getMessage()
    ]);
}
?>