<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        // Create new complaint
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        $requiredFields = ['customer_email', 'customer_name', 'subject', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required field: $field"]);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO complaints 
                (customer_email, customer_name, order_id, subject, message, status)
                VALUES 
                (:customer_email, :customer_name, :order_id, :subject, :message, 'Pending')
            ");

            $stmt->execute([
                ':customer_email' => $data['customer_email'],
                ':customer_name' => $data['customer_name'],
                ':order_id' => isset($data['order_id']) ? (int) $data['order_id'] : null,
                ':subject' => $data['subject'],
                ':message' => $data['message']
            ]);

            $complaintId = $pdo->lastInsertId();

            echo json_encode([
                "success" => true,
                "message" => "Complaint submitted successfully",
                "complaint_id" => $complaintId
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
        break;

    case 'GET':
        // Get complaints by customer email
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
                FROM complaints 
                WHERE customer_email = :email
            ");
            $stmtCount->execute([':email' => $email]);
            $total = (int) $stmtCount->fetchColumn();

            // Fetch complaints
            $stmt = $pdo->prepare("
                SELECT c.*, o.id as order_id_ref, o.total_price as order_total
                FROM complaints c
                LEFT JOIN orders o ON c.order_id = o.id
                WHERE c.customer_email = :email
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
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
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>

