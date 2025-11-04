<?php
/**
 * public/reset-password.php
 *
 * Forces the user to define a new password when flagged by an admin.
 */

use App\Auth;
use App\Database;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';

Auth::requireLogin();

if (!Auth::mustResetPassword()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['password_confirmation'] ?? '');

    if ($password === '' || $confirm === '') {
        $error = 'Informe e confirme a nova senha.';
    } elseif (strlen($password) < 6) {
        $error = 'A nova senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas informadas nao coincidem.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, must_reset_password = 0 WHERE id = :id');
        $stmt->execute([
            ':hash' => $hash,
            ':id' => $_SESSION['user_id'],
        ]);

        Auth::clearPasswordResetFlag();

        $_SESSION['flash_success'] = 'Senha redefinida com sucesso.';
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
    <title>Redefinir senha | GAC Leads</title>
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
            <img src="assets/images/logo.svg" alt="GAC Leads" class="h-10 w-auto">
        </div>
        <h1 class="text-2xl font-semibold text-blue-700 mb-2">Defina uma nova senha</h1>
        <p class="text-slate-500 mb-6">Por seguranca, cadastre uma nova senha antes de continuar.</p>

        <?php if ($error): ?>
            <div class="mb-4 rounded-lg bg-red-100 text-red-700 px-4 py-3 text-sm">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Nova senha</label>
                <input type="password" name="password" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Confirmar nova senha</label>
                <input type="password" name="password_confirmation" required class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition">
                Salvar nova senha
            </button>
        </form>
        <p class="text-xs text-slate-400 mt-6 text-center">
            Sua senha anterior deixara de funcionar apos salvar a nova senha.
        </p>
    </div>
</body>
</html>
