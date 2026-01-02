<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";

$method = $_SERVER['REQUEST_METHOD'];
$walletId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

switch ($method) {
    case 'GET':
        if ($walletId) {
            // Get single wallet by ID
            try {
                $stmt = $pdo->prepare("
                    SELECT w.*, 
                           (SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = w.id) as transaction_count
                    FROM wallets w
                    WHERE w.id = :id
                ");
                $stmt->execute([':id' => $walletId]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$wallet) {
                    http_response_code(404);
                    echo json_encode(["error" => "Wallet not found"]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "wallet" => $wallet
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } elseif ($email) {
            // Get wallet by email
            try {
                $stmt = $pdo->prepare("
                    SELECT w.*,
                           (SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = w.id) as transaction_count
                    FROM wallets w
                    WHERE w.customer_email = :email
                ");
                $stmt->execute([':email' => $email]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$wallet) {
                    http_response_code(404);
                    echo json_encode(["error" => "Wallet not found"]);
                    exit;
                }

                echo json_encode([
                    "success" => true,
                    "wallet" => $wallet
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } else {
            // Get all wallets with pagination and search
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 10;
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $offset = ($page - 1) * $limit;

            try {
                // Build search condition
                $searchCondition = "";
                $params = [];
                if ($search) {
                    $searchCondition = "WHERE customer_email LIKE :search";
                    $params[':search'] = "%$search%";
                }

                // Total count
                $countQuery = "SELECT COUNT(*) FROM wallets $searchCondition";
                $stmtCount = $pdo->prepare($countQuery);
                $stmtCount->execute($params);
                $total = (int) $stmtCount->fetchColumn();

                // Fetch wallets
                $query = "
                    SELECT w.*,
                           (SELECT COUNT(*) FROM wallet_transactions WHERE wallet_id = w.id) as transaction_count
                    FROM wallets w
                    $searchCondition
                    ORDER BY w.created_at DESC
                    LIMIT :limit OFFSET :offset
                ";
                $stmt = $pdo->prepare($query);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    "success" => true,
                    "wallets" => $wallets,
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
        // Create new wallet or add/withdraw funds
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON input"]);
            exit;
        }

        // Check if this is a transaction (add/withdraw) or creating a wallet
        if (isset($data['action']) && ($data['action'] === 'add' || $data['action'] === 'withdraw')) {
            // Handle wallet transaction
            $email = isset($data['email']) ? $data['email'] : '';
            $amount = isset($data['amount']) ? (float) $data['amount'] : 0;
            $description = isset($data['description']) ? $data['description'] : 'Manual adjustment';

            if (empty($email) || $amount <= 0) {
                http_response_code(400);
                echo json_encode(["error" => "Email and positive amount are required for transactions"]);
                exit;
            }

            try {
                $pdo->beginTransaction();

                // Get or create wallet
                $stmtWallet = $pdo->prepare("SELECT id, balance FROM wallets WHERE customer_email = :email FOR UPDATE");
                $stmtWallet->execute([':email' => $email]);
                $wallet = $stmtWallet->fetch(PDO::FETCH_ASSOC);

                if (!$wallet) {
                    // Create wallet if it doesn't exist
                    $stmtCreate = $pdo->prepare("INSERT INTO wallets (customer_email, balance) VALUES (:email, 0)");
                    $stmtCreate->execute([':email' => $email]);
                    $walletId = $pdo->lastInsertId();
                    $wallet = ['id' => $walletId, 'balance' => 0];
                } else {
                    $walletId = $wallet['id'];
                }

                // Check balance for withdrawal
                if ($data['action'] === 'withdraw' && $wallet['balance'] < $amount) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(["error" => "Insufficient wallet balance"]);
                    exit;
                }

                // Update balance
                $newBalance = $data['action'] === 'add' 
                    ? $wallet['balance'] + $amount 
                    : $wallet['balance'] - $amount;

                $stmtUpdate = $pdo->prepare("UPDATE wallets SET balance = :balance WHERE id = :id");
                $stmtUpdate->execute([
                    ':balance' => $newBalance,
                    ':id' => $walletId
                ]);

                // Record transaction
                $stmtTrans = $pdo->prepare("
                    INSERT INTO wallet_transactions 
                    (wallet_id, amount, type, description, reference_type)
                    VALUES 
                    (:wallet_id, :amount, :type, :description, 'manual')
                ");
                $stmtTrans->execute([
                    ':wallet_id' => $walletId,
                    ':amount' => $amount,
                    ':type' => $data['action'] === 'add' ? 'Credit' : 'Debit',
                    ':description' => $description
                ]);

                $pdo->commit();

                echo json_encode([
                    "success" => true,
                    "message" => "Transaction completed successfully",
                    "new_balance" => $newBalance
                ]);

            } catch (PDOException $e) {
                $pdo->rollBack();
                http_response_code(500);
                addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                echo json_encode(["error" => "Database error"]);
            }
        } else {
            // Create new wallet
            if (empty($data['customer_email'])) {
                http_response_code(400);
                echo json_encode(["error" => "customer_email is required"]);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wallets (customer_email, balance)
                    VALUES (:customer_email, :balance)
                ");

                $stmt->execute([
                    ':customer_email' => $data['customer_email'],
                    ':balance' => isset($data['balance']) ? (float) $data['balance'] : 0.00
                ]);

                $walletId = $pdo->lastInsertId();

                echo json_encode([
                    "success" => true,
                    "message" => "Wallet created successfully",
                    "wallet_id" => $walletId
                ]);

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    http_response_code(409);
                    echo json_encode(["error" => "Wallet already exists for this email"]);
                } else {
                    http_response_code(500);
                    addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
                    echo json_encode(["error" => "Database error"]);
                }
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>

