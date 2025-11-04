<?php
/**
 * public/admin/update-role.php
 *
 * Atualiza a role de um usuario (user/admin).
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
$newRole = $_POST['role'] ?? '';

if ($userId <= 0 || !in_array($newRole, ['user', 'admin'], true)) {
    $_SESSION['flash_error'] = 'Dados invalidos para atualizar o perfil.';
    header('Location: users.php');
    exit;
}

if ($userId === (int) ($_SESSION['user_id'] ?? 0) && $newRole !== 'admin') {
    $_SESSION['flash_error'] = 'Nao e permitido remover o proprio acesso de administrador.';
    header('Location: users.php');
    exit;
}

$pdo = Database::getConnection();

$pdo->beginTransaction();

$userStmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Usuario nao encontrado.';
    header('Location: users.php');
    exit;
}

if ($user['role'] === $newRole) {
    $pdo->rollBack();
    $_SESSION['flash_success'] = 'Nenhuma alteracao foi necessaria.';
    header('Location: users.php');
    exit;
}

if ($newRole === 'user') {
    $adminsStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id <> :id");
    $adminsStmt->execute([':id' => $userId]);
    $otherAdmins = (int) ($adminsStmt->fetchColumn() ?: 0);
    if ($otherAdmins === 0) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Nao e permitido remover o ultimo administrador do sistema.';
        header('Location: users.php');
        exit;
    }
}

$updateStmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
$updateStmt->execute([
    ':role' => $newRole,
    ':id' => $userId,
]);

$pdo->commit();

$message = $newRole === 'admin'
    ? sprintf('Usuario %s promovido a administrador.', $user['name'] ?? '#'.$userId)
    : sprintf('Usuario %s agora possui perfil padrao.', $user['name'] ?? '#'.$userId);

$_SESSION['flash_success'] = $message;

header('Location: users.php');
exit;

