<?php
$DB_HOST = 'localhost';
$DB_NAME = 'u453976845_Aditya';
$DB_USER = 'u453976845_kiran';
$DB_PASS = 'AdiKiran@123';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'DB connection failed','details'=>$e->getMessage()]);
    exit;
}

// === ADD THIS (server-side Gemini API key) ===
// Put your Gemini key here. DO NOT commit this file to public repos.
$gemini_api_key = 'AIzaSyBv8-BQxS21CPJzcBceFi-Fnf3hF2Uiy0o';

// optional helper kept
function read_json(){return json_decode(file_get_contents("php://input"),true);}