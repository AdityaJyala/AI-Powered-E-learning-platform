<?php
session_start();
require_once 'db.php';
// Redirect if not logged in
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$user_id   = (int)$_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Learner');

// ---------------------------
// Session & weak-areas endpoints
// ---------------------------

// POST to save session quiz results: php_file.php?action=submit_results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'submit_results') {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true) ?: $_POST;

    $score = isset($data['score']) ? (int)$data['score'] : 0;
    $total = isset($data['total']) ? (int)$data['total'] : 0;
    $correct = isset($data['correct']) ? (int)$data['correct'] : 0;
    $missed = $data['missed'] ?? []; // indices
    $missed_questions = $data['missed_questions'] ?? []; // full question objects from client
    $topic = $data['topic'] ?? ($_SESSION['last_generated_quiz']['topic'] ?? 'Unknown');

    // Store last quiz results
    $_SESSION['last_quiz_results'] = [
        'score' => $score,
        'total' => $total,
        'correct' => $correct,
        'missed' => $missed,
        'topic' => $topic,
        'timestamp' => date('c')
    ];

    // Store weak questions by topic in session (overwrite for topic)
    if (!empty($missed_questions) && is_array($missed_questions)) {
        if (!isset($_SESSION['weak_questions'])) $_SESSION['weak_questions'] = [];
        // Save as array of question objects under topic
        $_SESSION['weak_questions'][$topic] = array_values($missed_questions);
    } else {
        // If no missed questions for this topic, clear weak for topic
        if (isset($_SESSION['weak_questions'][$topic])) {
            unset($_SESSION['weak_questions'][$topic]);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'saved' => true, 'results' => $_SESSION['last_quiz_results'], 'weak' => $_SESSION['weak_questions'] ?? []]);
    exit;
}

// GET to return session stats: php_file.php?action=get_session_stats
if (isset($_GET['action']) && $_GET['action'] === 'get_session_stats') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'results' => $_SESSION['last_quiz_results'] ?? null]);
    exit;
}

// GET weak areas summary: php_file.php?action=get_weak_areas
if (isset($_GET['action']) && $_GET['action'] === 'get_weak_areas') {
    $weak = $_SESSION['weak_questions'] ?? [];
    // prepare summary: topic -> count
    $summary = [];
    foreach ($weak as $t => $qs) {
        $summary[] = ['topic' => $t, 'count' => count($qs)];
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'weak_areas' => $summary]);
    exit;
}

// GET weak questions for a topic: php_file.php?action=get_weak_questions&topic=...
if (isset($_GET['action']) && $_GET['action'] === 'get_weak_questions') {
    $topic = $_GET['topic'] ?? '';
    $weak = $_SESSION['weak_questions'] ?? [];
    $questions = $weak[$topic] ?? [];
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'topic'=>$topic, 'questions'=>$questions]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AI Edu Mentor • <?= $user_name ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Chart.js for charts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body { background: linear-gradient(135deg, #0f0f1e 0%, #1a0033 100%); min-height: 100vh; color: #f0f0ff; }
    .glass { background: rgba(255,255,255,0.07); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
    .card { @apply glass rounded-3xl p-6 border border-white/10 transition-all hover:border-white/20; }
    .chat-bubble { max-width: 85%; padding: 14px 20px; border-radius: 24px; animation: floatIn 0.5s ease-out; }
    .chat-user { background: linear-gradient(135deg, #8b5cf6, #3b82f6); color: white; margin-left: auto; }
    .chat-ai { background: rgba(255,255,255,0.08); color: #e0e7ff; margin-right: auto; }
    @keyframes floatIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
    .pulse-glow { animation: pulseGlow 3s infinite; }
    @keyframes pulseGlow { 0%,100% { box-shadow: 0 0 30px rgba(139,92,246,0.4); } 50% { box-shadow: 0 0 60px rgba(139,92,246,0.7); } }
    .streak-fire { font-size: 2.5rem; filter: drop-shadow(0 0 20px #f59e0b); }
    .question-card { transition: all 0.3s; }
    .question-card:hover { transform: translateX(8px); }
    .option-btn { transition: all 0.2s; }
    .option-btn:hover { transform: scale(1.02); }
    .option-btn.correct { background: linear-gradient(135deg, #10b981, #059669); }
    .option-btn.incorrect { background: linear-gradient(135deg, #ef4444, #dc2626); }
  </style>
</head>
<body class="antialiased">

<div class="fixed top-8 left-8 z-50 glass rounded-3xl px-6 py-4 flex items-center gap-4 pulse-glow border border-purple-500/30">
  <div class="streak-fire">🔥</div>
  <div>
    <div id="streakCount" class="text-4xl font-black bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">0</div>
    <div class="text-sm opacity-80">Day Streak</div>
  </div>
</div>

<button onclick="location.href='logout.php'" class="fixed top-8 right-8 z-50 glass rounded-full px-6 py-3 hover:scale-110 transition font-bold">
  Logout
</button>

<div class="max-w-7xl mx-auto px-6 py-20 pt-32">
  <div class="text-center mb-16">
    <h1 class="text-7xl font-black bg-gradient-to-r from-purple-400 via-pink-400 to-cyan-400 bg-clip-text text-transparent">
      Hello, <?= $user_name ?>!
    </h1>
    <p class="text-2xl mt-4 text-gray-300 font-light">Your AI tutor is ready to help you master any topic</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <main class="lg:col-span-2 space-y-8">
      <div class="card">
        <h3 class="text-xl font-bold mb-6 flex items-center gap-3">
          <i data-lucide="zap" class="w-8 h-8 text-yellow-400"></i> Quick Start
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <button id="openGenModalBtn" class="w-full py-5 bg-gradient-to-r from-violet-600 to-indigo-600 rounded-2xl text-white font-bold text-lg shadow-2xl hover:shadow-violet-500/50 transform hover:scale-105 transition">
            🎯 Generate Questions
          </button>
          <button id="practiceWeakBtn" class="w-full py-5 bg-gradient-to-r from-rose-600 to-pink-600 rounded-2xl text-white font-bold text-lg shadow-2xl hover:shadow-rose-500/50 transform hover:scale-105 transition">
            💡 Review Topics (Weak Areas)
          </button>
        </div>
      </div>

      <div id="quizSection" class="hidden">
        <div class="card p-8">
          <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-black" id="quizTopic">Quiz</h2>
            <div class="text-2xl font-bold">
              <span id="currentQ">1</span> / <span id="totalQ">5</span>
            </div>
          </div>
          
          <div id="questionContainer" class="space-y-6"></div>

          <div class="mt-8 flex justify-between">
            <button id="prevBtn" class="px-8 py-4 rounded-2xl glass font-bold hover:bg-white/10 transition">
              ← Previous
            </button>
            <button id="nextBtn" class="px-8 py-4 rounded-2xl bg-gradient-to-r from-purple-600 to-pink-600 font-bold hover:shadow-xl transition">
              Next →
            </button>
            <button id="submitQuizBtn" class="hidden px-12 py-4 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-xl hover:shadow-xl transition" type="button">
              🎉 Submit Quiz
            </button>
          </div>
        </div>
      </div>
      
      <div id="noQuizPlaceholder" class="card p-12 text-center space-y-4">
          <i data-lucide="brain-circuit" class="w-16 h-16 mx-auto text-purple-400"></i>
          <h2 class="text-3xl font-bold">Start Your Learning Journey!</h2>
          <p class="text-lg opacity-70">Use the **Generate Questions** button above to create your first practice quiz on any topic you want to master.</p>
      </div>
      
    </main>

    <aside class="lg:col-span-1 space-y-6">
      <div class="card flex flex-col h-96 lg:h-[520px] overflow-hidden">
        <div class="p-6 border-b border-white/10 flex items-center gap-4">
          <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-600 to-pink-600 p-1 animate-pulse">
            <div class="w-full h-full rounded-2xl bg-black/40 flex items-center justify-center">
              <i data-lucide="bot" class="w-10 h-10 text-white"></i>
            </div>
          </div>
          <div>
            <h3 class="text-2xl font-black">AI Mentor</h3>
            <p class="text-sm text-green-400">● Online</p>
          </div>
        </div>
        <div id="chatWindow" class="flex-1 overflow-y-auto p-6 space-y-4">
          <div class="chat-bubble chat-ai">
            👋 Hi! I'm your AI tutor. Ask me anything or request practice questions on any topic!
          </div>
        </div>
        <div class="p-6 pt-0 flex gap-3">
          <input id="messageInput" placeholder="Ask me anything..." class="flex-1 px-6 py-4 rounded-2xl glass focus:outline-none focus:ring-4 focus:ring-purple-500/50" />
          <button id="sendBtn" class="px-8 py-4 bg-gradient-to-r from-purple-600 to-pink-600 rounded-2xl font-bold hover:shadow-xl transform hover:scale-105 transition">
            Send
          </button>
        </div>
      </div>

      <!-- Session Statistics card -->
      <div id="sessionStatsCard" class="card p-4" style="display:none;">
        <h4 class="text-lg font-bold mb-3">Session Statistics (active)</h4>
        <div class="mb-3 text-sm text-gray-300">These stats persist until your session ends.</div>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <canvas id="scoreChart" height="160"></canvas>
          </div>
          <div>
            <canvas id="perQuestionChart" height="160"></canvas>
          </div>
        </div>
      </div>

    </aside>
  </div>
</div>

<!-- Generate modal reused for weak retake as well -->
<div id="genModal" class="fixed inset-0 bg-black/80 backdrop-blur-xl flex items-center justify-center z-50 hidden">
  <div class="glass rounded-3xl p-10 w-full max-w-2xl border border-white/20 shadow-2xl">
    <h2 class="text-4xl font-black mb-6 text-center bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
      Generate Practice Questions
    </h2>
    <div class="space-y-4">
      <div>
        <label class="text-lg font-bold mb-3 block">What do you want to learn?</label>
        <input id="topicInput" type="text" placeholder="e.g., Python Programming, World History, Calculus..." 
          class="w-full px-8 py-5 rounded-2xl glass text-lg focus:ring-4 focus:ring-purple-500/50" />
      </div>
      <div class="grid grid-cols-2 gap-6">
        <div>
          <label class="text-lg font-bold mb-3 block">Number of Questions</label>
          <input id="genCount" type="number" value="5" min="1" max="50" class="w-full px-6 py-5 rounded-2xl glass text-lg" />
        </div>
        <div>
          <label class="text-lg font-bold mb-3 block">Difficulty</label>
          <select id="genDifficulty" class="w-full px-6 py-5 rounded-2xl glass text-lg">
            <option value="easy">🌱 Easy</option>
            <option value="medium" selected>⚡ Medium</option>
            <option value="hard">🔥 Hard</option>
          </select>
        </div>
      </div>

      <div class="flex justify-center gap-6 mt-6">
        <button id="genCancel" class="px-12 py-5 rounded-2xl border-2 border-white/30 hover:bg-white/10 text-xl font-bold transition">
          Cancel
        </button>
        <button id="genSubmit" class="px-12 py-5 rounded-2xl bg-gradient-to-r from-purple-600 to-pink-600 text-xl font-bold hover:shadow-2xl transform hover:scale-105 transition">
          ✨ Generate
        </button>
        <!-- Retake weak questions (hidden by default) -->
        <button id="retakeWeakBtn" class="hidden px-12 py-5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 text-xl font-bold hover:shadow-2xl transform hover:scale-105 transition">
          🔁 Retake Weak Questions
        </button>
      </div>
    </div>
  </div>
</div>

<script>
lucide.createIcons();
const apiRoot = 'api.php'; // keep your api.php calls
const selfPath = window.location.pathname; // this file (for session endpoints)
let currentQuiz = null;
let currentQuestionIndex = 0;
let userAnswers = [];
let isReviewMode = false;
let currentQuizResults = null;

// Chart.js objects
let scoreChartObj = null;
let perQuestionChartObj = null;

// --- Initialization and Streak ---
async function loadDashboard() {
  updateStreak();
  document.getElementById('quizSection').classList.add('hidden');
  document.getElementById('noQuizPlaceholder').classList.remove('hidden');
  initSessionCharts();
  pollSessionStats(); // start poller
}

async function updateStreak() {
  try {
    const res = await fetch(`${apiRoot}?action=checkin`, {method: 'POST'});
    const json = await res.json().catch(()=>({streak:0}));
    const {streak} = json;
    document.getElementById('streakCount').textContent = streak || 0;
  } catch {
    document.getElementById('streakCount').textContent = 0;
  }
}
// ---------------------------------------------------

// Modal handlers
document.getElementById('openGenModalBtn').onclick = async () => {
  // When user opens generate modal manually, hide retake button
  document.getElementById('retakeWeakBtn').classList.add('hidden');
  document.getElementById('genSubmit').classList.remove('hidden');
  document.getElementById('genModal').classList.remove('hidden');
  document.getElementById('topicInput').value = '';
  document.getElementById('genCount').value = 5;
  document.getElementById('topicInput').focus();
};

document.getElementById('genCancel').onclick = () => {
  document.getElementById('genModal').classList.add('hidden');
};

document.getElementById('genModal').onclick = (e) => {
  if (e.target.id === 'genModal') document.getElementById('genModal').classList.add('hidden');
};

// Generate questions (normal)
document.getElementById('genSubmit').onclick = async () => {
  const topic = document.getElementById('topicInput').value.trim();
  if (!topic) { alert('Please enter a topic!'); return; }
  const count = parseInt(document.getElementById('genCount').value) || 5;
  const difficulty = document.getElementById('genDifficulty').value;
  const btn = document.getElementById('genSubmit');
  btn.disabled = true;
  btn.textContent = '✨ Generating...';

  try {
    const res = await fetch(`${apiRoot}?action=generate_questions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ topic, count, difficulty })
    });
    const data = await res.json();
    if (data.ok) {
      currentQuiz = { topic: data.topic, difficulty: data.difficulty, questions: data.generated };
      currentQuestionIndex = 0;
      userAnswers = new Array(data.generated.length).fill(null);
      isReviewMode = false;
      currentQuizResults = null;
      document.getElementById('genModal').classList.add('hidden');
      startQuiz();
      confetti({ particleCount: 200, spread: 90 });
    } else {
      alert(data.error || 'Failed to generate questions. Check API key in api.php.');
    }
  } catch (e) {
    alert('Error generating questions. Please check your API key and server connection.');
  } finally {
    btn.disabled = false;
    btn.textContent = '✨ Generate';
  }
};

// -------------------------------
// New: Practice weak areas handling
// -------------------------------
document.getElementById('practiceWeakBtn').onclick = async () => {
  try {
    const res = await fetch(selfPath + '?action=get_weak_areas');
    const data = await res.json();
    const weakAreas = (data.weak_areas || []);
    if (!weakAreas || weakAreas.length === 0) {
      alert('No weak areas found yet — you have no missed questions stored. Take a quiz to create weak areas.');
      // open modal to create quiz
      document.getElementById('openGenModalBtn').click();
      return;
    }

    // Choose top weak topic (the one with highest count)
    weakAreas.sort((a,b)=> b.count - a.count);
    const top = weakAreas[0];
    // Prefill modal with that topic and present Retake button
    document.getElementById('topicInput').value = top.topic;
    document.getElementById('genCount').value = top.count;
    document.getElementById('genSubmit').classList.add('hidden'); // hide normal generate
    const retakeBtn = document.getElementById('retakeWeakBtn');
    retakeBtn.classList.remove('hidden');

    // attach one-time handler to retake button
    retakeBtn.onclick = async () => {
      // fetch the weak questions for selected topic
      const qres = await fetch(selfPath + '?action=get_weak_questions&topic=' + encodeURIComponent(top.topic));
      const qdata = await qres.json();
      if (qdata.ok && qdata.questions && qdata.questions.length > 0) {
        // start a quiz using these weak questions directly
        // ensure question objects have required fields: question, options[], correct (index), explanation
        currentQuiz = {
          topic: qdata.topic,
          difficulty: 'weak',
          questions: qdata.questions
        };
        currentQuestionIndex = 0;
        userAnswers = new Array(qdata.questions.length).fill(null);
        isReviewMode = false;
        currentQuizResults = null;
        document.getElementById('genModal').classList.add('hidden');
        startQuiz();
      } else {
        alert('No weak questions found for this topic.');
      }
    };

    // show modal
    document.getElementById('genModal').classList.remove('hidden');
  } catch (e) {
    console.error(e);
    alert('Failed to load weak areas. Try again.');
  }
};

// Quiz functions (unchanged)
function startQuiz() {
  document.getElementById('quizSection').classList.remove('hidden');
  document.getElementById('noQuizPlaceholder').classList.add('hidden'); 
  document.getElementById('quizTopic').textContent = `${currentQuiz.topic} (${currentQuiz.difficulty})`;
  document.getElementById('totalQ').textContent = currentQuiz.questions.length;
  document.getElementById('prevBtn').classList.remove('hidden');
  document.getElementById('nextBtn').classList.remove('hidden');
  document.getElementById('nextBtn').textContent = 'Next →';
  document.getElementById('submitQuizBtn').classList.add('hidden');
  renderQuestion();
}

function renderQuestion() {
  isReviewMode = false;
  const q = currentQuiz.questions[currentQuestionIndex];
  const container = document.getElementById('questionContainer');
  container.innerHTML = `
    <div class="question-card p-8 bg-white/5 rounded-2xl">
      <div class="text-2xl font-bold mb-8">${q.question}</div>
      <div class="space-y-4">
        ${q.options.map((opt, i) => `
          <button onclick="selectAnswer(${i})" 
            class="option-btn w-full text-left p-6 rounded-2xl glass hover:bg-white/10 transition text-lg font-medium
            ${userAnswers[currentQuestionIndex] === i ? 'ring-4 ring-purple-500' : ''}">
            <span class="font-black text-purple-400 mr-4">${String.fromCharCode(65+i)}.</span> ${opt}
          </button>
        `).join('')}
      </div>
    </div>
  `;
  document.getElementById('currentQ').textContent = currentQuestionIndex + 1;
  document.getElementById('prevBtn').disabled = currentQuestionIndex === 0;
  if (currentQuestionIndex === currentQuiz.questions.length - 1) {
    document.getElementById('nextBtn').classList.add('hidden');
    document.getElementById('submitQuizBtn').classList.remove('hidden');
  } else {
    document.getElementById('nextBtn').classList.remove('hidden');
    document.getElementById('submitQuizBtn').classList.add('hidden');
  }
}

function selectAnswer(optionIndex) {
  if (isReviewMode) return;
  userAnswers[currentQuestionIndex] = optionIndex;
  renderQuestion();
}

// Navigation
document.getElementById('prevBtn').onclick = () => {
  if (isReviewMode) {
    if (currentQuestionIndex > 0) { currentQuestionIndex--; renderReviewQuestion(); }
  } else if (currentQuestionIndex > 0) { currentQuestionIndex--; renderQuestion(); }
};
document.getElementById('nextBtn').onclick = () => {
  if (isReviewMode) {
    if (currentQuestionIndex < currentQuizResults.missedQIndices.length - 1) { currentQuestionIndex++; renderReviewQuestion(); }
    else { document.getElementById('quizSection').classList.add('hidden'); loadDashboard(); }
  } else if (currentQuestionIndex < currentQuiz.questions.length - 1) { currentQuestionIndex++; renderQuestion(); }
};

// Submit quiz: now send missed_questions (full objects) to session endpoint
document.getElementById('submitQuizBtn').onclick = async () => {
  const unanswered = userAnswers.filter(a => a === null).length;
  if (unanswered > 0) {
    if (!window.confirm(`You have ${unanswered} unanswered questions. Submit anyway?`)) return;  
  }

  let correct = 0;
  let missedQIndices = [];
  let missedQuestions = [];

  currentQuiz.questions.forEach((q, i) => {
    if (userAnswers[i] !== null && userAnswers[i] === q.correct) {
      correct++;
    } else {
      missedQIndices.push(i);
      // push a minimal but complete question object for storage
      missedQuestions.push({
        question: q.question,
        options: q.options,
        correct: q.correct,
        explanation: q.explanation || ''
      });
    }
  });

  const score = Math.round((correct / currentQuiz.questions.length) * 100);

  currentQuizResults = {
    score,
    correct,
    total: currentQuiz.questions.length,
    missedQIndices: missedQIndices,
    topic: currentQuiz.topic
  };

  // 1) Save to your persistent API (existing)
  fetch(`${apiRoot}?action=submit_assessment`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      topic: currentQuizResults.topic,
      score: score,
      total_questions: currentQuizResults.total,
      correct_answers: correct
    })
  }).catch(()=>{ /* ignore */ });

  // 2) Save to this page's session store including missed_questions
  try {
    await fetch(selfPath + '?action=submit_results', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        topic: currentQuizResults.topic,
        score: score,
        total: currentQuizResults.total,
        correct: correct,
        missed: missedQIndices,
        missed_questions: missedQuestions
      })
    });
  } catch (e) {
    // ignore
  }

  // Show result summary and update charts
  confetti({ particleCount: score >= 70 ? 500 : 100, spread: 120 });
  const resultsMessage = `Quiz Complete!\n\nTopic: ${currentQuizResults.topic}\nScore: ${score}%\nCorrect: ${correct}/${currentQuizResults.total}`;
  window.alert(resultsMessage);
  fetchSessionStatsNow();

  // If questions were missed, show review button
  if (missedQIndices.length > 0) {
    const reviewBtn = document.createElement('button');
    reviewBtn.id = 'reviewMissedBtn';
    reviewBtn.type = 'button';
    reviewBtn.textContent = `Review ${missedQIndices.length} Missed Questions`;
    reviewBtn.className = 'w-full px-12 py-5 rounded-2xl bg-gradient-to-r from-teal-500 to-emerald-500 text-xl font-bold hover:shadow-2xl transform hover:scale-105 transition mt-8';
    reviewBtn.addEventListener('click', startReviewMissed);

    const summaryHtml = `
        <div class="text-center p-10 card">
            <div class="text-5xl mb-4">${score >= 85 ? '🏆' : score >= 50 ? '📈' : '💪'}</div>
            <h3 class="text-3xl font-black mb-2">Quiz Summary</h3>
            <p class="text-xl mb-6">Score: <span class="${score>=70 ? 'text-emerald-400' : 'text-rose-400'} font-black">${score}%</span> (${correct}/${currentQuizResults.total})</p>
            <div class="review-slot"></div>
            <button id="backToDashboardBtn" class="w-full px-12 py-3 rounded-2xl glass mt-4 font-bold transition">Back to Dashboard</button>
        </div>
    `;
    const container = document.getElementById('questionContainer');
    container.innerHTML = summaryHtml;
    const slot = container.querySelector('.review-slot');
    if (slot) slot.appendChild(reviewBtn);
    const backBtn = document.getElementById('backToDashboardBtn');
    if (backBtn) backBtn.addEventListener('click', () => {
        document.getElementById('quizSection').classList.add('hidden');
        loadDashboard();
    });
    document.getElementById('prevBtn').classList.add('hidden');
    document.getElementById('nextBtn').classList.add('hidden');
    document.getElementById('submitQuizBtn').classList.add('hidden');
  } else {
    document.getElementById('quizSection').classList.add('hidden');
    loadDashboard();
  }
};

// REVIEW MODE functions (unchanged)
function startReviewMissed() {
    if (!currentQuizResults || !currentQuizResults.missedQIndices || currentQuizResults.missedQIndices.length === 0) {
        alert('No missed questions to review.');
        return;
    }
    isReviewMode = true;
    currentQuestionIndex = 0;
    document.getElementById('quizTopic').textContent = `${currentQuizResults.topic} - Review Missed Questions`;
    document.getElementById('totalQ').textContent = currentQuizResults.missedQIndices.length;
    document.getElementById('prevBtn').classList.remove('hidden');
    document.getElementById('nextBtn').classList.remove('hidden');
    document.getElementById('nextBtn').textContent = 'Next Missed →';
    renderReviewQuestion();
}
function renderReviewQuestion() {
    if (!currentQuizResults || currentQuizResults.missedQIndices.length === 0) return;
    const missedIndex = currentQuizResults.missedQIndices[currentQuestionIndex];
    const q = currentQuiz.questions[missedIndex];
    const userAnswerIndex = userAnswers[missedIndex];
    const container = document.getElementById('questionContainer');
    container.innerHTML = `
        <div class="question-card p-8 bg-white/5 rounded-2xl">
            <div class="text-2xl font-bold mb-8">Missed Question ${currentQuestionIndex + 1}: ${q.question}</div>
            <div class="space-y-4">
                ${q.options.map((opt, i) => {
                    let className = 'option-btn w-full text-left p-6 rounded-2xl transition text-lg font-medium';
                    if (i === q.correct) { className += ' correct border-2 border-green-400 shadow-lg'; }
                    else if (i === userAnswerIndex) { className += ' incorrect border-2 border-red-400 shadow-lg'; }
                    else { className += ' glass hover:bg-white/10'; }
                    return `<button class="${className}"><span class="font-black text-purple-400 mr-4">${String.fromCharCode(65+i)}.</span> ${opt} ${i===q.correct? ' (Correct Answer)':''} ${i===userAnswerIndex? ' (Your Answer - Incorrect)':''}</button>`;
                }).join('')}
            </div>
            <div class="mt-8 p-6 bg-blue-500/10 rounded-2xl border border-blue-500/30">
                <div class="font-bold text-blue-400 mb-2">💡 AI Explanation:</div>
                <div class="text-lg">${(q.explanation||'No explanation available.')}</div>
            </div>
        </div>
        <div class="text-center mt-6">
            <button id="finishReviewBtn" class="px-8 py-3 rounded-2xl bg-white/10 hover:bg-white/20 font-bold transition">Finish Review & Return to Dashboard</button>
        </div>
    `;
    document.getElementById('currentQ').textContent = currentQuestionIndex + 1;
    document.getElementById('prevBtn').disabled = currentQuestionIndex === 0;
    if (currentQuestionIndex === currentQuizResults.missedQIndices.length - 1) {
        document.getElementById('nextBtn').classList.add('hidden');
    } else {
        document.getElementById('nextBtn').classList.remove('hidden');
    }
    const finishBtn = document.getElementById('finishReviewBtn');
    if (finishBtn) finishBtn.addEventListener('click', () => { document.getElementById('quizSection').classList.add('hidden'); loadDashboard(); });
}

// -------------------
// Session charts code
// -------------------
function initSessionCharts() {
  const scoreCtx = document.getElementById('scoreChart').getContext('2d');
  scoreChartObj = new Chart(scoreCtx, {
    type: 'doughnut',
    data: { labels: ['Score','Remaining'], datasets: [{ data: [0,100] }] },
    options: { cutout: '70%', plugins: { legend: { display: false } } }
  });

  const pCtx = document.getElementById('perQuestionChart').getContext('2d');
  perQuestionChartObj = new Chart(pCtx, {
    type: 'bar',
    data: { labels: ['Correct','Incorrect'], datasets: [{ label: 'Count', data: [0,0] }] },
    options: { scales: { y: { beginAtZero: true, precision: 0, ticks: { stepSize: 1 } } }, plugins: { legend:{display:false} } }
  });

  fetchSessionStatsNow();
}

async function fetchSessionStatsNow() {
  try {
    const res = await fetch(selfPath + '?action=get_session_stats');
    const json = await res.json();
    const stats = json.results;
    if (stats) {
      document.getElementById('sessionStatsCard').style.display = 'block';
      scoreChartObj.data.datasets[0].data = [stats.score, Math.max(0,100 - stats.score)];
      scoreChartObj.update();
      const total = stats.total || 0;
      const correct = stats.correct || 0;
      perQuestionChartObj.data.datasets[0].data = [correct, total - correct];
      perQuestionChartObj.update();
    }
  } catch (e) {
    // ignore
  }
}

function pollSessionStats() {
  setInterval(fetchSessionStatsNow, 5000);
}

// Chat functionality
async function sendMessage() {
  const input = document.getElementById('messageInput');
  const text = input.value.trim();
  if (!text) return;

  const chatWindow = document.getElementById('chatWindow');
  chatWindow.innerHTML += `<div class="chat-bubble chat-user">${text}</div>`;
  input.value = '';
  chatWindow.scrollTop = chatWindow.scrollHeight;

  try {
    const res = await fetch(`${apiRoot}?action=send_message`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text })
    });
    const data = await res.json();
    chatWindow.innerHTML += `<div class="chat-bubble chat-ai">${data.reply}</div>`;
    chatWindow.scrollTop = chatWindow.scrollHeight;
  } catch (e) {
    chatWindow.innerHTML += `<div class="chat-bubble chat-ai">Sorry, I'm having trouble responding. Please try again.</div>`;
  }
}

document.getElementById('sendBtn').onclick = sendMessage;
document.getElementById('messageInput').onkeypress = (e) => { if (e.key === 'Enter') sendMessage(); };

// Initialize
loadDashboard();
confetti({ particleCount: 150, spread: 80 });
</script>
</body>
</html>