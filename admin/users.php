<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

$method = $_SERVER['REQUEST_METHOD'];
$userId = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        if ($userId) {
            // Get single user by ID
            try {
                $stmt = $pdo->prepare("
                    SELECT id, first_name, last_name, email, phone, location, 
                           joined_at, membership, total_orders, total_spent, loyalty_points
                    FROM users
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    http_response_code(404);
                    echo json_encode(["error" => "User not found"]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "user" => $user
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } else {
            // Get all users with pagination and search
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $offset = ($page - 1) * $limit;

            try {
                // Build search condition
                $searchCondition = "";
                $params = [];
                if ($search) {
                    $searchCondition = "WHERE first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search";
                    $params[':search'] = "%$search%";
                }

                // Total count
                $countQuery = "SELECT COUNT(*) FROM users $searchCondition";
                $stmtCount = $pdo->prepare($countQuery);
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();

                // Fetch users
                $query = "
                    SELECT id, first_name, last_name, email, phone, location, 
                           joined_at, membership, total_orders, total_spent, loyalty_points
                    FROM users
                    $searchCondition
                    ORDER BY joined_at DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $pdo->prepare($query);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "success" => true,
                    "users" => $users,
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

    case 'POST':
        // Create new user
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        $requiredFields = ['first_name', 'last_name', 'email', 'joined_at'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required field: $field"]);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users 
                (first_name, last_name, email, phone, location, joined_at, membership, total_orders, total_spent, loyalty_points)
                VALUES 
                (:first_name, :last_name, :email, :phone, :location, :joined_at, :membership, :total_orders, :total_spent, :loyalty_points)
            ");

            $stmt->execute([
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':email' => $data['email'],
                ':phone' => isset($data['phone']) ? $data['phone'] : null,
                ':location' => isset($data['location']) ? $data['location'] : null,
                ':joined_at' => $data['joined_at'],
                ':membership' => isset($data['membership']) ? $data['membership'] : 'Member',
                ':total_orders' => isset($data['total_orders']) ? (int) $data['total_orders'] : 0,
                ':total_spent' => isset($data['total_spent']) ? (float) $data['total_spent'] : 0.00,
                ':loyalty_points' => isset($data['loyalty_points']) ? (int) $data['loyalty_points'] : 0
            ]);

            $userId = $pdo->lastInsertId();

            echo json_encode([
                "success" => true,
                "message" => "User created successfully",
                "user_id" => $userId
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                http_response_code(409);
                echo json_encode(["error" => "Email already exists"]);
            } else {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        }
        break;

    case 'PUT':
        // Update user - get ID from query param or request body
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$userId && isset($data['id'])) {
            $userId = (int) $data['id'];
        }
        
        if (!$userId) {
            http_response_code(400);
            echo json_encode(["error" => "User ID is required (use ?id= or include 'id' in body)"]);
            exit;
        }
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        try {
            // Build update query dynamically based on provided fields
            $updateFields = [];
            $params = [':id' => $userId];

            $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'location', 
                             'membership', 'total_orders', 'total_spent', 'loyalty_points'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(["error" => "No fields to update"]);
                exit;
            }

            $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "User not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "User updated successfully"
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                http_response_code(409);
                echo json_encode(["error" => "Email already exists"]);
            } else {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        }
        break;

    case 'DELETE':
        // Delete user
        if (!$userId) {
            http_response_code(400);
            echo json_encode(["error" => "User ID is required"]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "User not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "User deleted successfully"
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

