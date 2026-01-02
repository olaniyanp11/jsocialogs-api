<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
require_once "auth.php";


// ---------------------------
// Database configuration
// ---------------------------
$host = "localhost";
$db_name = "jsocialogs";
$username = "root";
$password = "";
$charset = "utf8mb4";

if (!defined('SECRET_KEY'))
    define('SECRET_KEY', 'your-strong-secret-key');
if (!defined('SECRET_IV'))
    define('SECRET_IV', '1234567890123456');
if (!defined('ENCRYPT_METHOD'))
    define('ENCRYPT_METHOD', 'AES-256-CBC');

function addLog(string $message, string $type = 'INFO', array $context = null)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (type, message, context, created_at)
            VALUES (:type, :message, :context, NOW())
        ");
        $stmt->execute([
            ':type' => $type,
            ':message' => $message,
            ':context' => $context ? json_encode($context) : null
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert log: " . $e->getMessage());
    }
}

try {
    $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);

    function encryptPassword($value)
    {
        return openssl_encrypt($value, ENCRYPT_METHOD, SECRET_KEY, 0, SECRET_IV);
    }

    function decryptPassword($value)
    {
        return openssl_decrypt($value, ENCRYPT_METHOD, SECRET_KEY, 0, SECRET_IV);
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ---------------------------
// Pagination & Search
// ---------------------------
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 5;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

try {
    // Total count
    $countQuery = "SELECT COUNT(*) FROM products WHERE name LIKE :search_name OR category LIKE :search_category";
    $stmtCount = $pdo->prepare($countQuery);
    $stmtCount->execute([
        ':search_name' => "%$search%",
        ':search_category' => "%$search%"
    ]);
    $total = (int) $stmtCount->fetchColumn();

    // Fetch products with limit & offset
    $stmt = $pdo->prepare("
        SELECT id, name, category, followers, quantity, price, tutorial_link, status, created_at, updated_at
        FROM products
        WHERE name LIKE :search_name OR category LIKE :search_category
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':search_name', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':search_category', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach accounts
    $stmtAccounts = $pdo->prepare("
        SELECT username, password, status
        FROM product_accounts
        WHERE product_id = :product_id
    ");

    foreach ($products as &$product) {
        $stmtAccounts->execute([':product_id' => $product['id']]);
        $accounts = $stmtAccounts->fetchAll(PDO::FETCH_ASSOC);
        foreach ($accounts as &$account) {
            $account['password'] = decryptPassword($account['password']);
        }
        $product['accounts'] = $accounts;
    }

    echo json_encode([
        "success" => true,
        "products" => $products,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "totalPages" => ceil($total / $limit)
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    addLog($e->getMessage(), "ERROR", [
        'file' => __FILE__,
        'line' => $e->getLine(),
        'search' => $search,
        'page' => $page,
        'limit' => $limit
    ]);
    echo json_encode(["error" => "Database error"]);
}
?>