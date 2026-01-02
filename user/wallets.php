<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

$method = $_SERVER['REQUEST_METHOD'];
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

switch ($method) {
    case 'GET':
        // Get wallet by email
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(["error" => "Email parameter is required"]);
            exit;
        }

        try {
            // Get wallet
            $stmt = $pdo->prepare("
                SELECT id, customer_email, balance, created_at, updated_at
                FROM wallets
                WHERE customer_email = :email
            ");
            $stmt->execute([':email' => $email]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                // Return zero balance if wallet doesn't exist
                echo json_encode([
                    "success" => true,
                    "wallet" => [
                        "customer_email" => $email,
                        "balance" => 0.00,
                        "exists" => false
                    ]
                ]);
                exit;
            }

            // Get recent transactions
            $stmtTransactions = $pdo->prepare("
                SELECT id, amount, type, description, reference_type, reference_id, created_at
                FROM wallet_transactions
                WHERE wallet_id = :wallet_id
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmtTransactions->execute([':wallet_id' => $wallet['id']]);
            $transactions = $stmtTransactions->fetchAll(PDO::FETCH_ASSOC);

            $wallet['transactions'] = $transactions;
            $wallet['exists'] = true;

            echo json_encode([
                "success" => true,
                "wallet" => $wallet
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

