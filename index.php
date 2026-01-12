<?php
// index.php - Login page
session_start();
require_once 'db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $err = 'Please fill both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: dashboard.php');
            exit;
        } else {
            $err = 'Invalid email or password.';
        }
    }
}

$success = $_GET['registered'] ?? '';
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <title>Sign In — AI Edu Mentor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          animation: {
            'float': 'float 6s ease-in-out infinite',
            'fadeInUp': 'fadeInUp 0.8s ease-out forwards',
            'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
            'glow': 'glow 2s ease-in-out infinite alternate',
          },
          keyframes: {
            float: {
              '0%, 100%': { transform: 'translateY(0)' },
              '50%': { transform: 'translateY(-20px)' }
            },
            fadeInUp: {
              '0%': { opacity: '0', transform: 'translateY(30px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' }
            },
            glow: {
              '0%': { boxShadow: '0 0 20px rgba(99, 102, 241, 0.3)' },
              '100%': { boxShadow: '0 0 40px rgba(99, 102, 241, 0.6)' }
            }
          }
        }
      }
    }
  </script>
  <style>
    .bg-gradient-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    input:focus {
      outline: none;
      ring: 0;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-50 via-indigo-50 to-pink-50 flex items-center justify-center p-6 relative overflow-hidden">
  <!-- Animated Background Elements -->
  <div class="absolute inset-0 pointer-events-none">
    <div class="absolute top-10 left-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-slow"></div>
    <div class="absolute top-40 right-20 w-96 h-96 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse-slow animation-delay-2000"></div>
    <div class="absolute -bottom-8 left-20 w-80 h-80 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-slow animation-delay-4000"></div>
  </div>

  <div class="relative z-10 w-full max-w-md">
    <!-- Main Card with subtle float animation -->
    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl p-10 border border-white/20 animate-float">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-primary rounded-2xl shadow-lg mb-6 animate-glow">
          <i class="fas fa-brain text-white text-4xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 animate-fadeInUp">Welcome Back</h1>
        <p class="text-gray-600 mt-2 animate-fadeInUp animation-delay-200">Sign in to continue to <span class="font-semibold text-indigo-600">AI Edu Mentor</span></p>
      </div>

      <!-- Success Message -->
      <?php if ($success): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl text-green-700 flex items-center gap-3 animate-fadeInUp">
          <i class="fas fa-check-circle text-xl"></i>
          <span>Account created successfully! Please sign in.</span>
        </div>
      <?php endif; ?>

      <!-- Error Message -->
      <?php if ($err): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl text-red-700 flex items-center gap-3 animate-fadeInUp">
          <i class="fas fa-exclamation-circle text-xl"></i>
          <span><?= htmlspecialchars($err) ?></span>
        </div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="post" class="space-y-6" novalidate>
        <div class="animate-fadeInUp animation-delay-400">
          <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-envelope text-gray-400"></i>
            </div>
            <input
              name="email"
              type="email"
              required
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 text-gray-900 placeholder-gray-400"
              placeholder="you@example.com"
              autocomplete="email"
            />
          </div>
        </div>

        <div class="animate-fadeInUp animation-delay-600">
          <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input
              name="password"
              type="password"
              required
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 text-gray-900 placeholder-gray-400"
              placeholder="••••••••••••"
              autocomplete="current-password"
            />
          </div>
        </div>

        <button
          type="submit"
          class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-3 text-lg animate-fadeInUp animation-delay-800"
        >
          <span>Sign In</span>
          <i class="fas fa-arrow-right"></i>
        </button>
      </form>

      <div class="mt-8 text-center">
        <p class="text-sm text-gray-600 animate-fadeInUp animation-delay-1000">
          Don't have an account?
          <a href="register.php" class="font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
            Create one now →
          </a>
        </p>
        <p class="mt-6 text-xs text-gray-500">
          <i class="fas fa-lightbulb text-yellow-500"></i>
          Tip: Use the register page to create a test account quickly.
        </p>
      </div>
    </div>

    <!-- Footer Branding -->
    <div class="text-center mt-8 text-gray-500 text-sm animate-fadeInUp animation-delay-1200">
      Powered by <span class="font-bold text-indigo-600">AI Edu Mentor</span> © 2025
    </div>
  </div>
</body>
</html>