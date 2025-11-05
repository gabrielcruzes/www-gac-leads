<?php
/**
 * public/register.php
 *
 * Tela de cadastro de novos usuarios com creditos iniciais.
 */

use App\Auth;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
$name = '';
$email = '';
$logoPath = file_exists(__DIR__ . '/assets/images/logo.png') ? 'assets/images/logo.png' : 'assets/images/logo.svg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Todos os campos sao obrigatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail valido.';
    } elseif (strlen($password) < 6) {
        $error = 'Escolha uma senha com pelo menos 6 caracteres.';
    } else {
        if (Auth::register($name, $email, $password)) {
            $_SESSION['flash_success'] = 'Cadastro realizado com sucesso! Entre com seu e-mail e senha.';
            header('Location: login.php');
            exit;
        }

        $error = 'Erro ao cadastrar. Verifique se o e-mail ja esta em uso.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta | GAC Leads</title>
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
        <h1 class="text-2xl font-semibold text-blue-700 mb-2">Crie sua conta</h1>
        <p class="text-slate-500 mb-6">Ganhe 5 creditos de boas-vindas e comece a explorar leads.</p>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-100 text-red-700 px-4 py-3 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nome completo</label>
                <input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" value="<?php echo htmlspecialchars($name); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">E-mail</label>
                <input type="email" name="email" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Senha</label>
                <input type="password" name="password" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition">
                Criar conta
            </button>
        </form>

        <p class="text-sm text-slate-500 mt-6 text-center">
            Ja tem uma conta?
            <a href="login.php" class="text-blue-600 hover:text-blue-700">Entre agora</a>
        </p>
    </div>
</body>
</html>
