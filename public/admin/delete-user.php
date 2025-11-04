<?php
/**
 * public/admin/delete-user.php
 *
 * Remove um usuario e seus dados relacionados.
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
    $_SESSION['flash_error'] = 'Usuario invalido para exclusao.';
    header('Location: users.php');
    exit;
}

if ($userId === $currentUserId) {
    $_SESSION['flash_error'] = 'Nao é permitido excluir a propria conta enquanto estiver logado.';
    header('Location: users.php');
    exit;
}

$pdo = Database::getConnection();

$stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash_error'] = 'Usuario nao encontrado.';
    header('Location: users.php');
    exit;
}

if (($user['role'] ?? 'user') === 'admin') {
    $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $totalAdmins = (int) ($adminCountStmt->fetchColumn() ?: 0);

    if ($totalAdmins <= 1) {
        $_SESSION['flash_error'] = 'Nao é possivel excluir o ultimo administrador do sistema.';
        header('Location: users.php');
        exit;
    }
}

$delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
$delete->execute([':id' => $userId]);

$_SESSION['flash_success'] = 'Usuario ' . ($user['name'] ?? '#'.$userId) . ' excluido com sucesso.';

header('Location: users.php');
exit;
