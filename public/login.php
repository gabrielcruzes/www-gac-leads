<?php
/**
 * public/login.php
 *
 * Tela de autenticação com layout moderno em Tailwind.
 */

use App\Auth;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);
$logoPath = file_exists(__DIR__ . '/assets/images/logo.png') ? 'assets/images/logo.png' : 'assets/images/logo.svg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Informe e-mail e senha.';
    } elseif (!Auth::login($email, $password)) {
        $error = 'Credenciais inválidas. Tente novamente.';
    } else {
        $redirect = Auth::isAdmin() ? 'admin/index.php' : 'index.php';
        header('Location: ' . $redirect);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | GAC Leads</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="shortcut icon" href="assets/images/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-10">
        <div class="flex justify-center mb-6">
            <img src="<?php echo $logoPath; ?>" alt="GAC Leads" class="h-10 w-auto">
        </div>
        <h1 class="text-2xl font-semibold text-blue-700 mb-2">Bem-vindo de volta</h1>
        <p class="text-slate-500 mb-6">Entre para acessar seus leads e créditos.</p>

        <?php if ($success): ?>
            <div class="mb-4 rounded-lg bg-green-100 text-green-700 px-4 py-3 text-sm">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-100 text-red-700 px-4 py-3 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">E-mail</label>
                <input type="email" name="email" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Senha</label>
                <input type="password" name="password" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition">
                Entrar
            </button>
        </form>

        <p class="text-sm text-slate-500 mt-6 text-center">
            Não possui conta?
            <a href="register.php" class="text-blue-600 hover:text-blue-700">Criar conta</a>
        </p>
    </div>
</body>
</html>
