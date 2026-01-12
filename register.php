<?php
// register.php - User registration
session_start();
require_once 'db.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($name === '') $errors[] = 'Full name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $in = $pdo->prepare("INSERT INTO users (name, email, password, created_at) VALUES (:name, :email, :password, NOW())");
            $in->execute([':name'=>$name, ':email'=>$email, ':password'=>$hash]);
            $success = true;
            header('Location: index.php?registered=1');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <title>Create Account — AI Edu Mentor</title>
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
    .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    input:focus {
      outline: none;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-50 via-indigo-50 to-pink-50 flex items-center justify-center p-6 relative overflow-hidden">
  <!-- Animated Background Orbs -->
  <div class="absolute inset-0 pointer-events-none">
    <div class="absolute top-10 left-10 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-slow"></div>
    <div class="absolute top-40 right-20 w-96 h-96 bg-indigo-300 rounded-full mix-blend-multiply filter blur-xl opacity-60 animate-pulse-slow animation-delay-2000"></div>
    <div class="absolute -bottom-8 left-20 w-80 h-80 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-pulse-slow animation-delay-4000"></div>
  </div>

  <div class="relative z-10 w-full max-w-md">
    <!-- Glassmorphic Card -->
    <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl p-10 border border-white/20 animate-float">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-primary rounded-2xl shadow-lg mb-6 animate-glow">
          <i class="fas fa-user-graduate text-white text-4xl"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 animate-fadeInUp">Join AI Edu Mentor</h1>
        <p class="text-gray-600 mt-2 animate-fadeInUp animation-delay-200">Create your account and start learning with AI</p>
      </div>

      <!-- Error Messages -->
      <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl text-red-700 animate-fadeInUp">
          <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-xl mt-0.5"></i>
            <ul class="list-disc list-inside space-y-1 text-sm">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <!-- Registration Form -->
      <form method="post" class="space-y-6" novalidate>
        <!-- Full Name -->
        <div class="animate-fadeInUp animation-delay-400">
          <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-user text-gray-400"></i>
            </div>
            <input
              name="name"
              type="text"
              required
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 placeholder-gray-400"
              placeholder="John Doe"
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
            />
          </div>
        </div>

        <!-- Email -->
        <div class="animate-fadeInUp animation-delay-500">
          <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-envelope text-gray-400"></i>
            </div>
            <input
              name="email"
              type="email"
              required
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 placeholder-gray-400"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            />
          </div>
        </div>

        <!-- Password -->
        <div class="animate-fadeInUp animation-delay-600">
          <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input
              id="password"
              name="password"
              type="password"
              required
              minlength="6"
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 placeholder-gray-400"
              placeholder="••••••••••••"
            />
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="animate-fadeInUp animation-delay-700">
          <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input
              id="password2"
              name="password2"
              type="password"
              required
              minlength="6"
              class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:border-indigo-500 transition-all duration-300 placeholder-gray-400"
              placeholder="Repeat your password"
            />
          </div>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-3 text-lg animate-fadeInUp animation-delay-800"
        >
          <span>Create Account</span>
          <i class="fas fa-sparkles"></i>
        </button>
      </form>

      <div class="mt-8 text-center animate-fadeInUp animation-delay-1000">
        <p class="text-sm text-gray-600">
          Already have an account?
          <a href="index.php" class="font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">
            Sign in here →
          </a>
        </p>
        <p class="mt-6 text-xs text-gray-500">
          <i class="fas fa-shield-alt text-green-500"></i>
          Your password is securely hashed and never stored in plain text.
        </p>
      </div>
    </div>

    <!-- Footer -->
    <div class="text-center mt-8 text-gray-500 text-sm animate-fadeInUp animation-delay-1200">
      Powered by <span class="font-bold text-indigo-600">AI Edu Mentor</span> © 2025
    </div>
  </div>
</body>
</html>