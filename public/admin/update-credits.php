<?php
/**
 * public/admin/update-credits.php
 *
 * Processa ajustes manuais de creditos feitos por administradores.
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
$amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
$action = $_POST['action'] ?? '';

if ($userId <= 0 || $amount <= 0 || !in_array($action, ['add', 'subtract'], true)) {
    $_SESSION['flash_error'] = 'Dados invalidos para ajuste de creditos.';
    header('Location: users.php');
    exit;
}

if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
    $_SESSION['flash_error'] = 'Utilize outra conta admin para ajustar seu proprio saldo.';
    header('Location: users.php');
    exit;
}

$pdo = Database::getConnection();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_credit_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        admin_id INT NOT NULL,
        change_amount INT NOT NULL,
        direction ENUM('add','subtract') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_credit_adjustments_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->beginTransaction();

$userStmt = $pdo->prepare('SELECT id, name, credits FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch();

if (!$user) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Usuario nao encontrado.';
    header('Location: users.php');
    exit;
}

$currentCredits = (int) $user['credits'];

if ($action === 'subtract' && $amount > $currentCredits) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Nao e possivel remover mais creditos do que o disponivel.';
    header('Location: users.php');
    exit;
}

$newCredits = $action === 'add'
    ? $currentCredits + $amount
    : $currentCredits - $amount;

$updateStmt = $pdo->prepare('UPDATE users SET credits = :credits WHERE id = :id');
$updateStmt->execute([
    ':credits' => $newCredits,
    ':id' => $userId,
]);

$logStmt = $pdo->prepare('
    INSERT INTO admin_credit_adjustments (user_id, admin_id, change_amount, direction)
    VALUES (:user_id, :admin_id, :change_amount, :direction)
');
$logStmt->execute([
    ':user_id' => $userId,
    ':admin_id' => (int) ($_SESSION['user_id'] ?? 0),
    ':change_amount' => $amount,
    ':direction' => $action,
]);

$pdo->commit();

$actionText = $action === 'add' ? 'adicionados' : 'removidos';
$_SESSION['flash_success'] = sprintf(
    '%d creditos %s com sucesso para %s.',
    $amount,
    $actionText,
    $user['name'] ?? 'o usuario'
);

header('Location: users.php');
exit;

