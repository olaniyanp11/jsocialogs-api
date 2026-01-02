<?php
require_once "../config.php";

try {
    $pdo->beginTransaction();

    // -----------------------------
    // Generate 50 products
    // -----------------------------
    $categories = ["Instagram", "Facebook", "Twitter", "TikTok"];
    $products = [];

    for ($i = 1; $i <= 50; $i++) {
        $category = $categories[array_rand($categories)];
        $products[] = [
            "name" => "$category Account #$i",
            "category" => $category,
            "followers" => rand(100, 50000),
            "quantity" => 0, // will update later
            "price" => rand(1000, 20000),
            "tutorial" => null,
            "status" => "Active"
        ];
    }

    $productStmt = $pdo->prepare("
        INSERT INTO products
        (name, category, followers, quantity, price, tutorial_link, status)
        VALUES (:name, :category, :followers, :quantity, :price, :tutorial, :status)
    ");

    $productIds = [];

    foreach ($products as $product) {
        $productStmt->execute($product);
        $productIds[] = $pdo->lastInsertId();
    }

    // -----------------------------
    // Generate 1-3 accounts per product
    // -----------------------------
    $accountStmt = $pdo->prepare("
        INSERT INTO product_accounts
        (product_id, username, password, status)
        VALUES (:product_id, :username, :password, 'Active')
    ");

    foreach ($productIds as $id) {
        $numAccounts = rand(1, 3);
        for ($j = 1; $j <= $numAccounts; $j++) {
            $username = "user_{$id}_$j";
            $password = "Pass@{$id}_$j";
            $accountStmt->execute([
                ":product_id" => $id,
                ":username" => $username,
                ":password" => encryptPassword($password)
            ]);
        }
    }

    // -----------------------------
    // Update product quantity automatically
    // -----------------------------
    $pdo->exec("
        UPDATE products p
        SET quantity = (
            SELECT COUNT(*) 
            FROM product_accounts pa 
            WHERE pa.product_id = p.id
        )
    ");

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "50 products and accounts seeded successfully (encrypted)"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>