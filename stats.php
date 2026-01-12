<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Learner');

// Get all statistics directly from database
$stats = [
    'topics' => [],
    'weak' => [],
    'history' => [],
    'streak' => 0,
    'total_quizzes' => 0,
    'avg_score' => 0
];

// 1. Get Topic Statistics
$query = "SELECT 
    topic,
    ROUND(AVG(score), 0) as avg_score,
    COUNT(*) as attempts,
    MAX(created_at) as last_attempt,
    SUM(correct_answers) as total_correct,
    SUM(total_questions) as total_questions
FROM assessments 
WHERE user_id = ?
GROUP BY topic
ORDER BY last_attempt DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['topics'][] = [
        'topic' => $row['topic'],
        'avg_score' => (int)$row['avg_score'],
        'attempts' => (int)$row['attempts'],
        'total_correct' => (int)$row['total_correct'],
        'total_questions' => (int)$row['total_questions']
    ];
}
$stmt->close();

// 2. Get Weak Areas (score < 70%)
$query = "SELECT 
    topic,
    ROUND(AVG(score), 0) as avg_score,
    COUNT(*) as attempts
FROM assessments 
WHERE user_id = ?
GROUP BY topic
HAVING avg_score < 70
ORDER BY avg_score ASC
LIMIT 5";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['weak'][] = [
        'topic' => $row['topic'],
        'avg_score' => (int)$row['avg_score'],
        'attempts' => (int)$row['attempts']
    ];
}
$stmt->close();

// 3. Get Practice History
$query = "SELECT 
    topic,
    score,
    total_questions,
    correct_answers,
    created_at
FROM assessments
WHERE user_id = ?
ORDER BY created_at DESC
LIMIT 10";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['history'][] = [
        'topic' => $row['topic'],
        'score' => (int)$row['score'],
        'total_questions' => (int)$row['total_questions'],
        'correct_answers' => (int)$row['correct_answers'],
        'date' => date('M j, Y', strtotime($row['created_at']))
    ];
}
$stmt->close();

// 4. Get User Streak
$query = "SELECT streak FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stats['streak'] = (int)($user_data['streak'] ?? 0);
$stmt->close();

// 5. Get Overall Statistics
$query = "SELECT 
    COUNT(*) as total_quizzes,
    ROUND(AVG(score), 0) as avg_score
FROM assessments
WHERE user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$overall = $result->fetch_assoc();
$stats['total_quizzes'] = (int)$overall['total_quizzes'];
$stats['avg_score'] = (int)$overall['avg_score'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Statistics • <?= $user_name ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body { background: linear-gradient(135deg, #0f0f1e 0%, #1a0033 100%); min-height: 100vh; color: #f0f0ff; }
    .glass { background: rgba(255,255,255,0.07); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
    .card { @apply glass rounded-3xl p-6 border border-white/10 transition-all hover:border-white/20; }
    .topic-card { transition: all 0.3s ease; }
    .topic-card:hover { transform: translateY(-8px) scale(1.03); box-shadow: 0 20px 40px rgba(139,92,246,0.3); }
    .pulse-glow { animation: pulseGlow 3s infinite; }
    @keyframes pulseGlow { 0%,100% { box-shadow: 0 0 30px rgba(139,92,246,0.4); } 50% { box-shadow: 0 0 60px rgba(139,92,246,0.7); } }
    .stat-badge { @apply text-center p-8 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-2xl border border-purple-500/30; }
  </style>
</head>
<body class="antialiased">

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 glass border-b border-white/10">
  <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
    <h1 class="text-2xl font-black bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
      📊 Statistics
    </h1>
    <div class="flex gap-4">
      <button onclick="location.href='dashboard.php'" class="px-6 py-3 glass rounded-2xl font-bold hover:bg-white/10 transition">
        ← Back to Dashboard
      </button>
      <button onclick="location.href='logout.php'" class="px-6 py-3 bg-red-600 rounded-2xl font-bold hover:bg-red-700 transition">
        Logout
      </button>
    </div>
  </div>
</nav>

<div class="max-w-7xl mx-auto px-6 py-20 pt-32">
  
  <!-- Overall Stats -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
    <div class="stat-badge pulse-glow">
      <div class="text-5xl mb-3">🔥</div>
      <div class="text-5xl font-black text-orange-400"><?= $stats['streak'] ?></div>
      <div class="text-lg font-semibold mt-2">Day Streak</div>
    </div>
    
    <div class="stat-badge">
      <div class="text-5xl mb-3">📝</div>
      <div class="text-5xl font-black text-blue-400"><?= $stats['total_quizzes'] ?></div>
      <div class="text-lg font-semibold mt-2">Total Quizzes</div>
    </div>
    
    <div class="stat-badge">
      <div class="text-5xl mb-3">⭐</div>
      <div class="text-5xl font-black text-yellow-400"><?= $stats['avg_score'] ?>%</div>
      <div class="text-lg font-semibold mt-2">Average Score</div>
    </div>
    
    <div class="stat-badge">
      <div class="text-5xl mb-3">📚</div>
      <div class="text-5xl font-black text-purple-400"><?= count($stats['topics']) ?></div>
      <div class="text-lg font-semibold mt-2">Topics Studied</div>
    </div>
  </div>

  <!-- Topics Progress -->
  <div class="card p-8 mb-8">
    <h2 class="text-4xl font-black mb-8 bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent flex items-center gap-4">
      <i data-lucide="bar-chart-3" class="w-12 h-12 text-cyan-400"></i>
      Topic Performance
    </h2>
    
    <?php if (empty($stats['topics'])): ?>
      <div class="text-center p-16 bg-white/5 rounded-2xl">
        <div class="text-7xl mb-6">🚀</div>
        <div class="text-3xl font-bold mb-4">No Statistics Yet</div>
        <div class="text-xl opacity-70 mb-8">Complete your first quiz to see your progress!</div>
        <button onclick="location.href='dashboard.php'" class="px-10 py-5 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl font-bold text-xl hover:shadow-2xl transform hover:scale-105 transition">
          Start Learning →
        </button>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($stats['topics'] as $topic): 
          $score = $topic['avg_score'];
          $emoji = $score >= 85 ? '🏆' : ($score >= 70 ? '⭐' : ($score >= 50 ? '📈' : '💪'));
          $color = $score >= 85 ? 'emerald' : ($score >= 60 ? 'amber' : 'rose');
        ?>
          <div class="topic-card p-8 bg-white/5 rounded-2xl">
            <div class="text-6xl mb-4"><?= $emoji ?></div>
            <div class="text-4xl font-black mb-2 text-<?= $color ?>-400">
              <?= $score ?>%
            </div>
            <div class="text-xl font-bold mb-4"><?= htmlspecialchars($topic['topic']) ?></div>
            <div class="flex justify-between text-sm opacity-70">
              <span>📊 <?= $topic['attempts'] ?> attempts</span>
              <span>✅ <?= $topic['total_correct'] ?>/<?= $topic['total_questions'] ?></span>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-4 bg-white/10 rounded-full h-3 overflow-hidden">
              <div class="h-full bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-400 transition-all duration-1000" 
                   style="width: <?= $score ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Weak Areas -->
    <div class="card p-8">
      <h3 class="text-3xl font-black mb-6 flex items-center gap-3 text-red-400">
        <i data-lucide="alert-triangle" class="w-10 h-10"></i> 
        Areas for Improvement
      </h3>
      
      <?php if (empty($stats['weak'])): ?>
        <div class="text-center p-12 bg-emerald-500/10 rounded-2xl border border-emerald-500/30">
          <div class="text-6xl mb-4">✨</div>
          <div class="text-2xl font-bold text-emerald-400 mb-2">Excellent Work!</div>
          <div class="text-lg opacity-80">All your topics are above 70%</div>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($stats['weak'] as $weak): ?>
            <div class="p-6 bg-red-500/10 rounded-2xl border border-red-500/30 hover:bg-red-500/20 transition">
              <div class="flex justify-between items-center mb-3">
                <div class="font-bold text-xl"><?= htmlspecialchars($weak['topic']) ?></div>
                <div class="text-3xl font-black text-red-400"><?= $weak['avg_score'] ?>%</div>
              </div>
              <div class="flex justify-between text-sm opacity-70">
                <span>Attempts: <?= $weak['attempts'] ?></span>
                <span>Need: <?= 70 - $weak['avg_score'] ?>% more</span>
              </div>
              <!-- Progress Bar -->
              <div class="mt-3 bg-white/10 rounded-full h-2 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-red-500 to-orange-400" 
                     style="width: <?= $weak['avg_score'] ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent History -->
    <div class="card p-8">
      <h3 class="text-3xl font-black mb-6 flex items-center gap-3 text-blue-400">
        <i data-lucide="history" class="w-10 h-10"></i> 
        Recent Activity
      </h3>
      
      <?php if (empty($stats['history'])): ?>
        <div class="text-center p-12 opacity-70">
          <div class="text-6xl mb-4">📭</div>
          <div class="text-xl">No practice history yet</div>
        </div>
      <?php else: ?>
        <div class="space-y-3 max-h-[600px] overflow-y-auto pr-2">
          <?php foreach ($stats['history'] as $h): 
            $color = $h['score'] >= 70 ? 'emerald' : 'rose';
          ?>
            <div class="p-5 bg-white/5 rounded-2xl hover:bg-white/10 transition">
              <div class="flex justify-between items-start mb-2">
                <div>
                  <div class="font-bold text-lg"><?= htmlspecialchars($h['topic']) ?></div>
                  <div class="text-sm opacity-70"><?= $h['date'] ?></div>
                </div>
                <div class="text-right">
                  <div class="text-3xl font-black text-<?= $color ?>-400">
                    <?= $h['score'] ?>%
                  </div>
                  <div class="text-sm opacity-70">
                    <?= $h['correct_answers'] ?>/<?= $h['total_questions'] ?> correct
                  </div>
                </div>
              </div>
              <!-- Mini Progress Bar -->
              <div class="bg-white/10 rounded-full h-2 overflow-hidden">
                <div class="h-full bg-gradient-to-r from-<?= $color ?>-500 to-<?= $color ?>-400" 
                     style="width: <?= $h['score'] ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Performance Chart -->
  <?php if (!empty($stats['history'])): ?>
  <div class="card p-8 mt-8">
    <h3 class="text-3xl font-black mb-6 flex items-center gap-3">
      <i data-lucide="trending-up" class="w-10 h-10 text-green-400"></i>
      Score Trend
    </h3>
    <canvas id="performanceChart" class="w-full" height="100"></canvas>
  </div>
  <?php endif; ?>

</div>

<script>
lucide.createIcons();

<?php if (!empty($stats['history'])): ?>
// Performance Chart
const ctx = document.getElementById('performanceChart').getContext('2d');
const chartData = <?= json_encode(array_reverse($stats['history'])) ?>;

new Chart(ctx, {
  type: 'line',
  data: {
    labels: chartData.map(h => h.topic + ' (' + h.date + ')'),
    datasets: [{
      label: 'Score %',
      data: chartData.map(h => h.score),
      borderColor: 'rgb(139, 92, 246)',
      backgroundColor: 'rgba(139, 92, 246, 0.1)',
      tension: 0.4,
      fill: true,
      pointRadius: 6,
      pointHoverRadius: 8,
      pointBackgroundColor: 'rgb(139, 92, 246)',
      pointBorderColor: '#fff',
      pointBorderWidth: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 12,
        titleFont: { size: 14, weight: 'bold' },
        bodyFont: { size: 13 }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        max: 100,
        grid: { color: 'rgba(255, 255, 255, 0.1)' },
        ticks: { 
          color: '#fff',
          callback: value => value + '%'
        }
      },
      x: {
        grid: { display: false },
        ticks: { 
          color: '#fff',
          maxRotation: 45,
          minRotation: 45
        }
      }
    }
  }
});
<?php endif; ?>

// Auto-refresh every 30 seconds
setInterval(() => {
  location.reload();
}, 30000);
</script>

</body>
</html>