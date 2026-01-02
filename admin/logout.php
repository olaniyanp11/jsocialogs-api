<?php
header('Content-Type: application/json; charset=utf-8');
require_once "../config.php";
session_start();

session_unset();
session_destroy();

echo json_encode([
    "success" => true,
    "message" => "Logged out successfully"
]);
