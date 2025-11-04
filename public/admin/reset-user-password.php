<?php
/**
 * public/admin/reset-user-password.php
 *
 * Marca um usuario para redefinir a senha no proximo acesso.
 */

use App\Auth;
use App\Database;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['flash_error'] = 'Usuario invalido para reset de senha.';
    header('Location: users.php');
    exit;
}

if ($userId === $currentUserId) {
    $_SESSION['flash_error'] = 'Use outra conta administrativa para forcar o reset da propria senha.';
    header('Location: users.php');
    exit;
}

$pdo = Database::getConnection();

$stmt = $pdo->prepare('SELECT id, name, must_reset_password FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash_error'] = 'Usuario nao encontrado.';
    header('Location: users.php');
    exit;
}

if ((int) ($user['must_reset_password'] ?? 0) === 1) {
    $_SESSION['flash_success'] = 'O usuario ' . ($user['name'] ?? '#'.$userId) . ' ja esta com reset pendente.';
    header('Location: users.php');
    exit;
}

$update = $pdo->prepare('UPDATE users SET must_reset_password = 1 WHERE id = :id');
$update->execute([':id' => $userId]);

$_SESSION['flash_success'] = 'O usuario ' . ($user['name'] ?? '#'.$userId) . ' precisara cadastrar uma nova senha no proximo acesso.';

header('Location: users.php');
exit;
