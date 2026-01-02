<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

$method = $_SERVER['REQUEST_METHOD'];

// Only allow GET requests
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$walletId = isset($_GET['wallet_id']) ? (int) $_GET['wallet_id'] : null;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

try {
    if ($walletId) {
        // Get transactions by wallet ID
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50;
        $offset = ($page - 1) * $limit;

        // Total count
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wallet_transactions 
            WHERE wallet_id = :wallet_id
        ");
        $stmtCount->execute([':wallet_id' => $walletId]);
        $total = (int) $stmtCount->fetchColumn();

        // Fetch transactions
        $stmt = $pdo->prepare("
            SELECT wt.*, w.customer_email
            FROM wallet_transactions wt
            LEFT JOIN wallets w ON wt.wallet_id = w.id
            WHERE wt.wallet_id = :wallet_id
            ORDER BY wt.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':wallet_id', $walletId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "transactions" => $transactions,
            "pagination" => [
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "totalPages" => ceil($total / $limit)
            ]
        ]);

    } elseif ($email) {
        // Get transactions by email
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50;
        $offset = ($page - 1) * $limit;

        // Get wallet ID first
        $stmtWallet = $pdo->prepare("SELECT id FROM wallets WHERE customer_email = :email");
        $stmtWallet->execute([':email' => $email]);
        $wallet = $stmtWallet->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            http_response_code(404);
            echo json_encode(["error" => "Wallet not found"]);
            exit;
        }

        // Total count
        $stmtCount = $pdo->prepare("
            SELECT COUNT(*) 
            FROM wallet_transactions 
            WHERE wallet_id = :wallet_id
        ");
        $stmtCount->execute([':wallet_id' => $wallet['id']]);
        $total = (int) $stmtCount->fetchColumn();

        // Fetch transactions
        $stmt = $pdo->prepare("
            SELECT wt.*, w.customer_email
            FROM wallet_transactions wt
            LEFT JOIN wallets w ON wt.wallet_id = w.id
            WHERE wt.wallet_id = :wallet_id
            ORDER BY wt.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':wallet_id', $wallet['id'], PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "transactions" => $transactions,
            "pagination" => [
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "totalPages" => ceil($total / $limit)
            ]
        ]);

    } else {
        // Get all transactions
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 50;
        $type = isset($_GET['type']) ? trim($_GET['type']) : '';
        $offset = ($page - 1) * $limit;

        $conditions = [];
        $params = [];

        if ($type) {
            $conditions[] = "wt.type = :type";
            $params[':type'] = $type;
        }

        $whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

        // Total count
        $countQuery = "SELECT COUNT(*) FROM wallet_transactions wt $whereClause";
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Fetch transactions
        $query = "
            SELECT wt.*, w.customer_email
            FROM wallet_transactions wt
            LEFT JOIN wallets w ON wt.wallet_id = w.id
            $whereClause
            ORDER BY wt.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $pdo->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "transactions" => $transactions,
            "pagination" => [
                "page" => $page,
                "limit" => $limit,
                "total" => $total,
                "totalPages" => ceil($total / $limit)
            ]
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
    echo json_encode(["error" => "Database error"]);
}
?>

