<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$reference = isset($_GET['reference']) ? trim($_GET['reference']) : '';

if (empty($reference)) {
    http_response_code(400);
    echo json_encode(["error" => "Payment reference is required"]);
    exit;
}

try {
    // Verify payment with Paystack
    $paystackResponse = verifyPaystackPayment($reference);

    if (!$paystackResponse || !isset($paystackResponse['status']) || !$paystackResponse['status']) {
        http_response_code(400);
        addLog("Paystack verification failed: " . json_encode($paystackResponse), "ERROR");
        echo json_encode([
            "error" => "Payment verification failed",
            "details" => isset($paystackResponse['message']) ? $paystackResponse['message'] : 'Unknown error'
        ]);
        exit;
    }

    $paymentData = $paystackResponse['data'];
    $paymentStatus = $paymentData['status'] === 'success' ? 'success' : 'failed';
    $orderId = isset($paymentData['metadata']['order_id']) ? (int) $paymentData['metadata']['order_id'] : null;

    if (!$orderId) {
        // Try to get order by payment reference
        $stmtOrder = $pdo->prepare("SELECT id FROM orders WHERE payment_reference = :reference");
        $stmtOrder->execute([':reference' => $reference]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);
        $orderId = $order ? $order['id'] : null;
    }

    if (!$orderId) {
        http_response_code(404);
        echo json_encode(["error" => "Order not found for this payment reference"]);
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Update order payment status
    $stmtUpdateOrder = $pdo->prepare("
        UPDATE orders 
        SET payment_status = :payment_status,
            status = CASE 
                WHEN :payment_status = 'success' THEN 'Completed'
                ELSE status
            END
        WHERE id = :order_id
    ");
    $stmtUpdateOrder->execute([
        ':payment_status' => $paymentStatus,
        ':order_id' => $orderId
    ]);

    // Update payment record if exists
    try {
        $stmtUpdatePayment = $pdo->prepare("
            UPDATE payments 
            SET status = :payment_status,
                paystack_response = :paystack_response,
                updated_at = NOW()
            WHERE payment_reference = :reference
        ");
        $stmtUpdatePayment->execute([
            ':payment_status' => $paymentStatus,
            ':paystack_response' => json_encode($paystackResponse),
            ':reference' => $reference
        ]);
    } catch (PDOException $e) {
        // Payments table might not exist, that's okay
    }

    // If payment successful, update user stats and create wallet transaction if needed
    if ($paymentStatus === 'success') {
        // Get order details
        $stmtOrderDetails = $pdo->prepare("
            SELECT customer_email, total_price 
            FROM orders 
            WHERE id = :order_id
        ");
        $stmtOrderDetails->execute([':order_id' => $orderId]);
        $orderDetails = $stmtOrderDetails->fetch(PDO::FETCH_ASSOC);

        if ($orderDetails) {
            // Update user stats
            $stmtUser = $pdo->prepare("
                SELECT id, total_orders, total_spent, loyalty_points
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmtUser->execute([':email' => $orderDetails['customer_email']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $newLoyaltyPoints = floor($orderDetails['total_price']);
                $stmtUpdateUser = $pdo->prepare("
                    UPDATE users 
                    SET total_orders = total_orders + 1,
                        total_spent = total_spent + :total_price,
                        loyalty_points = loyalty_points + :loyalty_points
                    WHERE id = :id
                ");
                $stmtUpdateUser->execute([
                    ':total_price' => (float) $orderDetails['total_price'],
                    ':loyalty_points' => $newLoyaltyPoints,
                    ':id' => $user['id']
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "message" => "Payment verified successfully",
        "payment_status" => $paymentStatus,
        "order_id" => $orderId,
        "reference" => $reference,
        "amount" => isset($paymentData['amount']) ? $paymentData['amount'] / 100 : null,
        "paid_at" => isset($paymentData['paid_at']) ? $paymentData['paid_at'] : null
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    addLog($e->getMessage(), "ERROR", ['file' => __FILE__, 'line' => $e->getLine()]);
    echo json_encode(["error" => "Error: " . $e->getMessage()]);
}
?>

