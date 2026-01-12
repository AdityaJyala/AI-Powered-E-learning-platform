<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'db.php';
// ==================== YOUR GEMINI API KEY HERE ====================
$GEMINI_API_KEY = "AIzaSyBv8-BQxS21CPJzcBceFi-Fnf3hF2Uiy0o"; // ← YOUR KEY
// ==================================================================
// Real AI function using Gemini 2.0 Flash (updated)
function ai($prompt) {
    global $GEMINI_API_KEY;
   
    if (empty($GEMINI_API_KEY) || str_contains($GEMINI_API_KEY, 'XXXX')) {
        return "Sample AI response (add your real Gemini API key in api.php)";
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$GEMINI_API_KEY}";
    $payload = [
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 2048,
            "topP" => 0.8
        ]
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        error_log("Gemini API Error: " . $response);
        return "I'm having trouble connecting. Please try again.";
    }
    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? "I'm thinking...";
    // Clean formatting
    $text = preg_replace('/^##?\s*/m', '', $text);
    $text = str_replace(['**', '* '], ['', '• '], $text);
    return trim($text);
}
// Auth
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && !in_array($_GET['action'] ?? '', ['health'])) {
    echo json_encode(['error' => 'Login required']);
    exit;
}
$action = $_GET['action'] ?? null;
switch ($action) {
    case 'health':
        echo json_encode(['ok' => true]);
        break;
    case 'get_dashboard':
        // Get user's practice history with topics
        $stmt = $pdo->prepare("
            SELECT topic,
                   AVG(score) as avg_score,
                   COUNT(*) as attempts,
                   MAX(created_at) as last_attempt
            FROM user_assessments
            WHERE user_id = ?
            GROUP BY topic
            ORDER BY avg_score ASC
        ");
        $stmt->execute([$user_id]);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
        // Calculate weak areas (score < 70)
        $weak = array_filter($topics, fn($t) => $t['avg_score'] < 70);
        echo json_encode([
            'topics' => array_values($topics),
            'weak' => array_values($weak)
        ]);
        break;
    case 'generate_questions':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $topic = trim($input['topic'] ?? '');
        $count = max(1, min(15, intval($input['count'] ?? 5)));
        $difficulty = in_array($input['difficulty'] ?? '', ['easy','medium','hard']) ? $input['difficulty'] : 'medium';
        if (empty($topic)) {
            echo json_encode(['ok' => false, 'error' => 'Please specify a topic']);
            break;
        }
        $prompt = "You are an expert educator. Generate exactly $count $difficulty-level multiple-choice questions about: $topic
IMPORTANT: Return ONLY a valid JSON array with this exact format (no markdown, no explanation):
[
  {
    \"question\": \"Question text here?\",
    \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
    \"correct\": 0,
    \"explanation\": \"Brief explanation why this is correct\"
  }
]
The 'correct' field should be the index (0-3) of the correct option.
Make questions practical and test real understanding.";
        $response = ai($prompt);
       
        // Clean JSON from markdown
        $response = preg_replace('/```json\s*|\s*```/', '', $response);
        $response = trim($response);
       
        $questions = json_decode($response, true);
       
        if (!is_array($questions) || empty($questions)) {
            // Fallback: parse line by line if JSON fails
            $lines = array_filter(array_map('trim', explode("\n", $response)));
            $questions = [];
            foreach (array_slice($lines, 0, $count) as $i => $line) {
                $questions[] = [
                    'question' => preg_replace('/^\d+[\.)]\s*/', '', $line),
                    'options' => ['True', 'False', 'Not sure', 'Need more info'],
                    'correct' => 0,
                    'explanation' => 'Practice question on ' . $topic
                ];
            }
        }
        echo json_encode([
            'ok' => true,
            'generated' => $questions,
            'topic' => $topic,
            'difficulty' => $difficulty
        ]);
        break;
    // Removed case 'submit_assessment': to prevent saving quiz data to DB

    case 'get_weak_areas':
        $stmt = $pdo->prepare("
            SELECT topic,
                   AVG(score) as avg_score,
                   COUNT(*) as attempts
            FROM user_assessments
            WHERE user_id = ?
            GROUP BY topic
            HAVING avg_score < 70
            ORDER BY avg_score ASC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $weak = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['weak_areas' => $weak]);
        break;
    case 'send_message':
        $input = json_decode(file_get_contents('php://input'), true);
        $text = trim($input['text'] ?? '');
       
        if (!$text) {
            echo json_encode(['error' => 'Empty message']);
            break;
        }
        // Get user's weak areas for context
        $stmt = $pdo->prepare("
            SELECT topic, AVG(score) as avg_score
            FROM user_assessments
            WHERE user_id = ?
            GROUP BY topic
            HAVING avg_score < 70
            ORDER BY avg_score ASC
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $weak = $stmt->fetchAll(PDO::FETCH_ASSOC);
       
        $context = "";
        if (!empty($weak)) {
            $context = "\n\nUser's weak areas: " . implode(', ', array_column($weak, 'topic'));
        }
        $prompt = "You are a friendly, expert AI tutor helping a student. Be encouraging and helpful.$context
Student asks: \"$text\"
Respond concisely and helpfully:";
        $reply = ai($prompt);
        echo json_encode(['ok' => true, 'reply' => $reply]);
        break;
    case 'checkin':
        // Daily streak tracking
        $stmt = $pdo->prepare("
            SELECT DATEDIFF(CURDATE(), MAX(DATE(created_at))) as days_since_last
            FROM user_assessments
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
       
        $days_since = $result['days_since_last'] ?? 999;
       
        // Get current streak
        $stmt = $pdo->prepare("SELECT streak FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_streak = $stmt->fetchColumn() ?: 0;
       
        // Update streak logic
        if ($days_since === 0) {
            // Already checked in today
            $streak = $current_streak;
        } elseif ($days_since === 1) {
            // Consecutive day
            $streak = $current_streak + 1;
            $stmt = $pdo->prepare("UPDATE users SET streak = ? WHERE id = ?");
            $stmt->execute([$streak, $user_id]);
        } else {
            // Streak broken
            $streak = 0;
            $stmt = $pdo->prepare("UPDATE users SET streak = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        echo json_encode(['ok' => true, 'streak' => $streak]);
        break;
    case 'leaderboard':
        $stmt = $pdo->query("
            SELECT u.name,
                   COALESCE(AVG(ua.score), 0) as avg_score,
                   COALESCE(COUNT(ua.id), 0) as total_assessments,
                   u.streak
            FROM users u
            LEFT JOIN user_assessments ua ON u.id = ua.user_id
            GROUP BY u.id
            ORDER BY avg_score DESC, total_assessments DESC
            LIMIT 10
        ");
        $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['leaders' => $leaders]);
        break;
    case 'get_practice_history':
        $stmt = $pdo->prepare("
            SELECT topic, score, correct_answers, total_questions, created_at
            FROM user_assessments
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['history' => $history]);
        break;
    default:
        echo json_encode(['error' => 'Unknown action']);
}