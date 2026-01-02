<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";


// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

// Read JSON input
$data = json_decode(file_get_contents("php://input"), true);

$requiredFields = ['product_id', 'name', 'category', 'accounts', 'price'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required field: $field"]);
        exit;
    }
}

$productId = (int) $data['product_id'];
$accounts = $data['accounts']; // array of { username, password }

// Validate accounts
if (!is_array($accounts) || count($accounts) === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Accounts must be a non-empty array"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Update product details
    $stmtProduct = $pdo->prepare("
        UPDATE products SET
        name = :name,
        category = :category,
        followers = :followers,
        price = :price,
        tutorial_link = :tutorial_link,
        status = :status
        WHERE id = :id
    ");

    $stmtProduct->execute([
        ':name' => $data['name'],
        ':category' => $data['category'],
        ':followers' => isset($data['followers']) ? (int) $data['followers'] : 0,
        ':price' => (float) $data['price'],
        ':tutorial_link' => isset($data['tutorialLink']) ? $data['tutorialLink'] : null,
        ':status' => isset($data['status']) ? $data['status'] : 'Active',
        ':id' => $productId
    ]);

    // Delete existing accounts
    $stmtDeleteAccounts = $pdo->prepare("DELETE FROM product_accounts WHERE product_id = :product_id");
    $stmtDeleteAccounts->execute([':product_id' => $productId]);

    // Insert new accounts
    $stmtInsertAccount = $pdo->prepare("
        INSERT INTO product_accounts (product_id, username, password, status)
        VALUES (:product_id, :username, :password, :status)
    ");

    $validAccounts = 0;
    foreach ($accounts as $account) {
        if (empty($account['username']) || empty($account['password']))
            continue;

        $stmtInsertAccount->execute([
            ':product_id' => $productId,
            ':username' => $account['username'],
            ':password' => encryptPassword($account['password']),
            ':status' => isset($account['status']) ? $account['status'] : 'Active'
        ]);

        $validAccounts++;
    }

    // Update quantity
    $stmtUpdateQuantity = $pdo->prepare("UPDATE products SET quantity = :quantity WHERE id = :id");
    $stmtUpdateQuantity->execute([
        ':quantity' => $validAccounts,
        ':id' => $productId
    ]);

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Product updated successfully",
        "accounts_updated" => $validAccounts
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        "error" => "Database error: " . $e->getMessage()
    ]);
}
?>