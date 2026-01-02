<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

$method = $_SERVER['REQUEST_METHOD'];
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        if ($orderId) {
            // Get single order by ID
            try {
                $stmt = $pdo->prepare("
                    SELECT o.*, p.name as product_name, p.category as product_category
                    FROM orders o
                    LEFT JOIN products p ON o.product_id = p.id
                    WHERE o.id = :id
                ");
                $stmt->execute([':id' => $orderId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    http_response_code(404);
                    echo json_encode(["error" => "Order not found"]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "order" => $order
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } else {
            // Get all orders with pagination, search, and filters
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $offset = ($page - 1) * $limit;

            try {
                // Build conditions
                $conditions = [];
                $params = [];

                if ($search) {
                    $conditions[] = "(o.customer_name LIKE :search OR o.customer_email LIKE :search OR p.name LIKE :search)";
                    $params[':search'] = "%$search%";
                }

                if ($status) {
                    $conditions[] = "o.status = :status";
                    $params[':status'] = $status;
                }

                $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

                // Total count
                $countQuery = "
                    SELECT COUNT(*) 
                    FROM orders o
                    LEFT JOIN products p ON o.product_id = p.id
                    $whereClause
                ";
                $stmtCount = $pdo->prepare($countQuery);
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();

                // Fetch orders
                $query = "
                    SELECT o.*, p.name as product_name, p.category as product_category
                    FROM orders o
                    LEFT JOIN products p ON o.product_id = p.id
                    $whereClause
                    ORDER BY o.created_at DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $pdo->prepare($query);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
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
        }
        break;

    case 'PUT':
        // Update order - get ID from query param or request body
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$orderId && isset($data['id'])) {
            $orderId = (int) $data['id'];
        }
        
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(["error" => "Order ID is required (use ?id= or include 'id' in body)"]);
            exit;
        }
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        try {
            $updateFields = [];
            $params = [':id' => $orderId];

            $allowedFields = ['status', 'quantity', 'total_price'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    if ($field === 'status') {
                        $params[":$field"] = $data[$field];
                    } elseif ($field === 'quantity') {
                        $params[":$field"] = (int) $data[$field];
                    } else {
                        $params[":$field"] = (float) $data[$field];
                    }
                }
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(["error" => "No fields to update"]);
                exit;
            }

            // Validate status if provided
            if (isset($data['status'])) {
                $validStatuses = ['Pending', 'Completed', 'Cancelled'];
                if (!in_array($data['status'], $validStatuses)) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid status. Must be one of: " . implode(', ', $validStatuses)]);
                    exit;
                }
            }

            $query = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Order not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "Order updated successfully"
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
            echo json_encode(["error" => "Database error"]);
        }
        break;

    case 'DELETE':
        // Delete order
        if (!$orderId) {
            http_response_code(400);
            echo json_encode(["error" => "Order ID is required"]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = :id");
            $stmt->execute([':id' => $orderId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Order not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "Order deleted successfully"
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

