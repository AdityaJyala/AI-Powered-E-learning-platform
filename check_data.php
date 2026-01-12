<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
    die('Please login first');
}

$user_id = (int)$_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Data Check</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #1a1a2e; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #16213e; }
        th, td { padding: 12px; text-align: left; border: 1px solid #0f3460; }
        th { background: #533483; }
        h2 { color: #e94560; }
        .success { color: #2ecc71; }
        .error { color: #e74c3c; }
        .info { background: #533483; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>

<h1>🔍 Database Data Check</h1>

<div class="info">
    <strong>User ID:</strong> <?= $user_id ?><br>
    <strong>Session Name:</strong> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Unknown') ?>
</div>

<h2>📊 Assessments Table Data</h2>
<?php
$query = "SELECT * FROM assessments WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0):
?>
    <p class="success">✅ Found <?= $result->num_rows ?> quiz records</p>
    <table>
        <tr>
            <th>ID</th>
            <th>Topic</th>
            <th>Score</th>
            <th>Questions</th>
            <th>Correct</th>
            <th>Date</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['topic']) ?></td>
            <td><strong><?= $row['score'] ?>%</strong></td>
            <td><?= $row['total_questions'] ?></td>
            <td><?= $row['correct_answers'] ?></td>
            <td><?= $row['created_at'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p class="error">❌ No quiz records found! Complete a quiz first.</p>
<?php endif; ?>

<h2>📈 Topic Statistics</h2>
<?php
$query = "SELECT 
    topic,
    ROUND(AVG(score), 0) as avg_score,
    COUNT(*) as attempts,
    MIN(score) as min_score,
    MAX(score) as max_score
FROM assessments 
WHERE user_id = ?
GROUP BY topic
ORDER BY avg_score DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0):
?>
    <table>
        <tr>
            <th>Topic</th>
            <th>Avg Score</th>
            <th>Attempts</th>
            <th>Min Score</th>
            <th>Max Score</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['topic']) ?></td>
            <td><strong><?= $row['avg_score'] ?>%</strong></td>
            <td><?= $row['attempts'] ?></td>
            <td><?= $row['min_score'] ?>%</td>
            <td><?= $row['max_score'] ?>%</td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p class="error">❌ No topic statistics available</p>
<?php endif; ?>

<h2>⚠️ Weak Areas (< 70%)</h2>
<?php
$query = "SELECT 
    topic,
    ROUND(AVG(score), 0) as avg_score,
    COUNT(*) as attempts
FROM assessments 
WHERE user_id = ?
GROUP BY topic
HAVING avg_score < 70
ORDER BY avg_score ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0):
?>
    <table>
        <tr>
            <th>Topic</th>
            <th>Avg Score</th>
            <th>Attempts</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['topic']) ?></td>
            <td><strong style="color: #e74c3c"><?= $row['avg_score'] ?>%</strong></td>
            <td><?= $row['attempts'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p class="success">✅ No weak areas! All topics are above 70%</p>
<?php endif; ?>

<h2>🔥 User Streak</h2>
<?php
$query = "SELECT streak, last_checkin FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<div class="info">
    <strong>Current Streak:</strong> <?= $user['streak'] ?? 0 ?> days<br>
    <strong>Last Check-in:</strong> <?= $user['last_checkin'] ?? 'Never' ?>
</div>

<hr style="margin: 40px 0; border-color: #533483;">

<div style="text-align: center;">
    <a href="dashboard.php" style="display: inline-block; padding: 15px 40px; background: #533483; color: white; text-decoration: none; border-radius: 10px; font-weight: bold;">
        ← Back to Dashboard
    </a>
    <a href="stats.php" style="display: inline-block; padding: 15px 40px; background: #e94560; color: white; text-decoration: none; border-radius: 10px; font-weight: bold; margin-left: 10px;">
        📊 View Stats Page
    </a>
</div>

</body>
</html>