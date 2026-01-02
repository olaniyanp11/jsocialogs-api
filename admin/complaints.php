<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

$method = $_SERVER['REQUEST_METHOD'];
$complaintId = isset($_GET['id']) ? (int) $_GET['id'] : null;

switch ($method) {
    case 'GET':
        if ($complaintId) {
            // Get single complaint by ID
            try {
                $stmt = $pdo->prepare("
                    SELECT c.*, o.id as order_id_ref, o.total_price as order_total,
                           a.username as admin_username
                    FROM complaints c
                    LEFT JOIN orders o ON c.order_id = o.id
                    LEFT JOIN admins a ON c.admin_id = a.id
                    WHERE c.id = :id
                ");
                $stmt->execute([':id' => $complaintId]);
                $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$complaint) {
                    http_response_code(404);
                    echo json_encode(["error" => "Complaint not found"]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "complaint" => $complaint
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } else {
            // Get all complaints with pagination, search, and filters
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $email = isset($_GET['email']) ? trim($_GET['email']) : '';
            $offset = ($page - 1) * $limit;

            try {
                // Build conditions
                $conditions = [];
                $params = [];

                if ($search) {
                    $conditions[] = "(c.subject LIKE :search OR c.message LIKE :search OR c.customer_name LIKE :search OR c.customer_email LIKE :search)";
                    $params[':search'] = "%$search%";
                }

                if ($status) {
                    $conditions[] = "c.status = :status";
                    $params[':status'] = $status;
                }

                if ($email) {
                    $conditions[] = "c.customer_email = :email";
                    $params[':email'] = $email;
                }

                $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

                // Total count
                $countQuery = "SELECT COUNT(*) FROM complaints c $whereClause";
                $stmtCount = $pdo->prepare($countQuery);
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();

                // Fetch complaints
                $query = "
                    SELECT c.*, o.id as order_id_ref, o.total_price as order_total
                    FROM complaints c
                    LEFT JOIN orders o ON c.order_id = o.id
                    $whereClause
                    ORDER BY c.created_at DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $pdo->prepare($query);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "success" => true,
                    "complaints" => $complaints,
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
        // Update complaint (mainly status and admin response)
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$complaintId && isset($data['id'])) {
            $complaintId = (int) $data['id'];
        }
        
        if (!$complaintId) {
            http_response_code(400);
            echo json_encode(["error" => "Complaint ID is required (use ?id= or include 'id' in body)"]);
            exit;
        }
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        try {
            $updateFields = [];
            $params = [':id' => $complaintId];

            $allowedFields = ['status', 'admin_response'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $data[$field];
                }
            }

            // Always update admin_id when status/admin_response is updated
            if (!empty($updateFields)) {
                $updateFields[] = "admin_id = :admin_id";
                $params[':admin_id'] = $_SESSION['admin_id'];
            }

            if (empty($updateFields)) {
                http_response_code(400);
                echo json_encode(["error" => "No fields to update"]);
                exit;
            }

            // Validate status if provided
            if (isset($data['status'])) {
                $validStatuses = ['Pending', 'In Progress', 'Resolved', 'Closed'];
                if (!in_array($data['status'], $validStatuses)) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid status. Must be one of: " . implode(', ', $validStatuses)]);
                    exit;
                }
            }

            $query = "UPDATE complaints SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Complaint not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "Complaint updated successfully"
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
            echo json_encode(["error" => "Database error"]);
        }
        break;

    case 'DELETE':
        // Delete complaint
        if (!$complaintId) {
            http_response_code(400);
            echo json_encode(["error" => "Complaint ID is required"]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = :id");
            $stmt->execute([':id' => $complaintId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["error" => "Complaint not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "message" => "Complaint deleted successfully"
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

