<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

try {
    // Get total users
    $stmtUsers = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = (int) $stmtUsers->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total products
    $stmtProducts = $pdo->query("SELECT COUNT(*) as total FROM products");
    $totalProducts = (int) $stmtProducts->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active products
    $stmtActiveProducts = $pdo->query("SELECT COUNT(*) as total FROM products WHERE status = 'Active'");
    $activeProducts = (int) $stmtActiveProducts->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total orders
    $stmtOrders = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = (int) $stmtOrders->fetch(PDO::FETCH_ASSOC)['total'];

    // Get orders by status
    $stmtOrdersByStatus = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
    ");
    $ordersByStatus = [];
    while ($row = $stmtOrdersByStatus->fetch(PDO::FETCH_ASSOC)) {
        $ordersByStatus[$row['status']] = (int) $row['count'];
    }

    // Get total revenue (completed orders)
    $stmtRevenue = $pdo->query("
        SELECT COALESCE(SUM(total_price), 0) as total 
        FROM orders 
        WHERE status = 'Completed'
    ");
    $totalRevenue = (float) $stmtRevenue->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending revenue
    $stmtPendingRevenue = $pdo->query("
        SELECT COALESCE(SUM(total_price), 0) as total 
        FROM orders 
        WHERE status = 'Pending'
    ");
    $pendingRevenue = (float) $stmtPendingRevenue->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total accounts
    $stmtAccounts = $pdo->query("SELECT COUNT(*) as total FROM product_accounts");
    $totalAccounts = (int) $stmtAccounts->fetch(PDO::FETCH_ASSOC)['total'];

    // Get active accounts
    $stmtActiveAccounts = $pdo->query("SELECT COUNT(*) as total FROM product_accounts WHERE status = 'Active'");
    $activeAccounts = (int) $stmtActiveAccounts->fetch(PDO::FETCH_ASSOC)['total'];

    // Get recent orders (last 10)
    $stmtRecentOrders = $pdo->query("
        SELECT o.*, p.name as product_name
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $recentOrders = $stmtRecentOrders->fetchAll(PDO::FETCH_ASSOC);

    // Get top selling products
    $stmtTopProducts = $pdo->query("
        SELECT p.id, p.name, p.category, COUNT(o.id) as order_count, SUM(o.total_price) as revenue
        FROM products p
        LEFT JOIN orders o ON p.id = o.product_id AND o.status = 'Completed'
        GROUP BY p.id, p.name, p.category
        ORDER BY order_count DESC, revenue DESC
        LIMIT 5
    ");
    $topProducts = $stmtTopProducts->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "stats" => [
            "users" => [
                "total" => $totalUsers
            ],
            "products" => [
                "total" => $totalProducts,
                "active" => $activeProducts
            ],
            "accounts" => [
                "total" => $totalAccounts,
                "active" => $activeAccounts,
                "inactive" => $totalAccounts - $activeAccounts
            ],
            "orders" => [
                "total" => $totalOrders,
                "by_status" => $ordersByStatus
            ],
            "revenue" => [
                "total" => $totalRevenue,
                "pending" => $pendingRevenue
            ],
            "recent_orders" => $recentOrders,
            "top_products" => $topProducts
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
    echo json_encode(["error" => "Database error"]);
}
?>

