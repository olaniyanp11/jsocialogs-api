<?php
require_once "../config.php";

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['username']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(["error" => "Username and password are required"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, username, password, role, status
        FROM admins
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $data['username']
    ]);

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($data['password'], $admin['password'])) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid credentials"]);
        exit;
    }

    if ($admin['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(["error" => "Account disabled"]);
        exit;
    }

    // SESSION STORAGE
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        "admin" => [
            "id" => $admin['id'],
            "username" => $admin['username'],
            "role" => $admin['role']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Server error"]);
}
