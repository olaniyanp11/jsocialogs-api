<?php
require_once "../config.php";

$username = "admin@jsocialogs.com";
$password = "JSOCIAL123@!!";

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO admins (username, password, status, role)
    VALUES (:username, :password, 'active', 'superadmin')
");

$stmt->execute([
    ':username' => $username,
    ':password' => $hash
]);

echo "Admin created successfully";
