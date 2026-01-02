<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Create new order
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        $requiredFields = ['product_id', 'quantity', 'customer_name', 'customer_email'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required field: $field"]);
                exit;
            }
        }

        try {
            // Verify product exists and is active
            $stmtProduct = $pdo->prepare("
                SELECT id, price, quantity, status
                FROM products
                WHERE id = :id AND status = 'Active'
            ");
            $stmtProduct->execute([':id' => (int) $data['product_id']]);
            $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                http_response_code(404);
                echo json_encode(["error" => "Product not found or not available"]);
                exit;
            }

            $quantity = (int) $data['quantity'];
            if ($quantity <= 0) {
                http_response_code(400);
                echo json_encode(["error" => "Quantity must be greater than 0"]);
                exit;
            }

            // Check if enough accounts available
            if ($product['quantity'] < $quantity) {
                http_response_code(400);
                echo json_encode(["error" => "Insufficient stock. Available: " . $product['quantity']]);
                exit;
            }

            // Calculate total price
            $totalPrice = $product['price'] * $quantity;

            // Begin transaction
            $pdo->beginTransaction();

            // Create order
            $stmtOrder = $pdo->prepare("
                INSERT INTO orders 
                (product_id, quantity, total_price, customer_name, customer_email, status)
                VALUES 
                (:product_id, :quantity, :total_price, :customer_name, :customer_email, 'Pending')
            ");

            $stmtOrder->execute([
                ':product_id' => (int) $data['product_id'],
                ':quantity' => $quantity,
                ':total_price' => $totalPrice,
                ':customer_name' => $data['customer_name'],
                ':customer_email' => $data['customer_email']
            ]);

            $orderId = $pdo->lastInsertId();

            // Update product quantity (reduce available stock)
            $stmtUpdateProduct = $pdo->prepare("
                UPDATE products 
                SET quantity = quantity - :quantity 
                WHERE id = :id
            ");
            $stmtUpdateProduct->execute([
                ':quantity' => $quantity,
                ':id' => (int) $data['product_id']
            ]);

            // Mark accounts as inactive (reserve them)
            $stmtReserveAccounts = $pdo->prepare("
                UPDATE product_accounts 
                SET status = 'Inactive'
                WHERE product_id = :product_id AND status = 'Active'
                LIMIT :quantity
            ");
            $stmtReserveAccounts->bindValue(':product_id', (int) $data['product_id'], PDO::PARAM_INT);
            $stmtReserveAccounts->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            $stmtReserveAccounts->execute();

            // Update or create user stats
            $stmtUser = $pdo->prepare("
                SELECT id, total_orders, total_spent, loyalty_points
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmtUser->execute([':email' => $data['customer_email']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Update existing user
                $newLoyaltyPoints = floor($totalPrice); // 1 point per dollar spent
                $stmtUpdateUser = $pdo->prepare("
                    UPDATE users 
                    SET total_orders = total_orders + 1,
                        total_spent = total_spent + :total_price,
                        loyalty_points = loyalty_points + :loyalty_points
                    WHERE id = :id
                ");
                $stmtUpdateUser->execute([
                    ':total_price' => $totalPrice,
                    ':loyalty_points' => $newLoyaltyPoints,
                    ':id' => $user['id']
                ]);
            } else {
                // Create new user (optional - if you want to auto-create users from orders)
                // For now, we'll skip this as it requires first_name and last_name
            }

            $pdo->commit();

            echo json_encode([
                "success" => true,
                "message" => "Order created successfully",
                "order_id" => $orderId,
                "total_price" => $totalPrice
            ]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
        break;

    case 'GET':
        // Get orders by customer email
        $email = isset($_GET['email']) ? trim($_GET['email']) : '';

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(["error" => "Email parameter is required"]);
            exit;
        }

        try {
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
            $offset = ($page - 1) * $limit;

            // Total count
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) 
                FROM orders 
                WHERE customer_email = :email
            ");
            $stmtCount->execute([':email' => $email]);
            $total = (int) $stmtCount->fetchColumn();

            // Fetch orders
            $stmt = $pdo->prepare("
                SELECT o.*, p.name as product_name, p.category as product_category
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.customer_email = :email
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "orders" => $orders,
                "pagination" => [
                    "page" => $page,
                    "limit" => $limit,
                    "total" => $total,
                    "totalPages" => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
            echo json_encode(["error" => "Database error"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>

