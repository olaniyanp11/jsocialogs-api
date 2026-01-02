<?php
// ---------------------------
// CORS Setup (allow all origins for dev)
// ---------------------------
$allowedOrigins = [
    "http://localhost:3000",
    "http://127.0.0.1:3000",
    "http://localhost",
];

// Detect request origin
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}


// ---------------------------
// Database configuration
// ---------------------------
$host = "localhost";
$db_name = "jsocialogs"; // replace with your DB name
$username = "root";         // replace with your MySQL username
$password = "";             // replace with your MySQL password
$charset = "utf8mb4";

// Encryption setup (if not already defined)
if (!defined('SECRET_KEY'))
    define('SECRET_KEY', 'your-strong-secret-key'); // must match your encryption key
if (!defined('SECRET_IV'))
    define('SECRET_IV', '1234567890123456'); // 16 bytes IV
if (!defined('ENCRYPT_METHOD'))
    define('ENCRYPT_METHOD', 'AES-256-CBC'); // encryption method

// Paystack configuration
if (!defined('PAYSTACK_SECRET_KEY'))
    define('PAYSTACK_SECRET_KEY', 'sk_test_a11875e17b4146b173ee6aee33aaef269ab6ffb9');
if (!defined('PAYSTACK_PUBLIC_KEY'))
    define('PAYSTACK_PUBLIC_KEY', 'pk_test_YOUR_PUBLIC_KEY'); // You'll need to add your public key
if (!defined('PAYSTACK_API_URL'))
    define('PAYSTACK_API_URL', 'https://api.paystack.co');

// Paystack helper function
function initializePaystackPayment($email, $amount, $reference, $options = []) {
    $url = PAYSTACK_API_URL . "/transaction/initialize";
    
    $fields = [
        'email' => $email,
        'amount' => $amount * 100, // Convert to kobo (smallest currency unit)
        'reference' => $reference,
    ];

    // Add callback_url if provided
    if (isset($options['callback_url']) && !empty($options['callback_url'])) {
        $fields['callback_url'] = $options['callback_url'];
    }

    // Add metadata if provided
    if (isset($options['metadata']) && is_array($options['metadata'])) {
        $fields['metadata'] = $options['metadata'];
    }

    $fields_string = http_build_query($fields);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    return json_decode($result, true);
}

function verifyPaystackPayment($reference) {
    $url = PAYSTACK_API_URL . "/transaction/verify/" . $reference;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ));
    
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        return ['success' => false, 'error' => $err];
    }
    
    return json_decode($result, true);
}

function addLog(string $message, string $type = 'INFO', array $context = null)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (type, message, context)
            VALUES (:type, :message, :context)
        ");
        $stmt->execute([
            ':type' => $type,
            ':message' => $message,
            ':context' => $context ? json_encode($context) : null
        ]);
    } catch (PDOException $e) {
        // Optional: if logging fails, do nothing to avoid infinite loops
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
    // echo "Connected successfully"; // uncomment for testing
    function encryptPassword($value)
    {
        return openssl_encrypt(
            $value,
            ENCRYPT_METHOD,
            SECRET_KEY,
            0,
            SECRET_IV
        );
    }

    function decryptPassword($value)
    {
        return openssl_decrypt(
            $value,
            ENCRYPT_METHOD,
            SECRET_KEY,
            0,
            SECRET_IV
        );
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>