<?php
/**
 * public/admin/index.php
 *
 * Painel inicial com visao geral para administradores.
 */

use App\Auth;
use App\Database;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/components.php';

Auth::requireAdmin();

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

$totalUsersStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalUsers = (int) ($totalUsersStmt->fetchColumn() ?: 0);

$totalAdminsStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$totalAdmins = (int) ($totalAdminsStmt->fetchColumn() ?: 0);

$usersLast7DaysStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$usersLast7Days = (int) ($usersLast7DaysStmt->fetchColumn() ?: 0);

$sumCreditsStmt = $pdo->query("SELECT SUM(credits) FROM users WHERE role = 'user'");
$sumCredits = (int) ($sumCreditsStmt->fetchColumn() ?: 0);

$recentUsersStmt = $pdo->prepare("SELECT id, name, email, credits, created_at FROM users ORDER BY created_at DESC LIMIT 8");
$recentUsersStmt->execute();
$recentUsers = $recentUsersStmt->fetchAll() ?: [];

$adjustmentsStmt = $pdo->prepare("
    SELECT aca.change_amount, aca.direction, aca.created_at,
           u.name AS user_name, u.email AS user_email,
           admin.name AS admin_name
    FROM admin_credit_adjustments aca
    JOIN users u ON u.id = aca.user_id
    JOIN users admin ON admin.id = aca.admin_id
    ORDER BY aca.created_at DESC
    LIMIT 8
");
$adjustmentsStmt->execute();
$recentAdjustments = $adjustmentsStmt->fetchAll() ?: [];

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

renderAdminPageStart('Visao geral', 'dashboard');
?>

<?php if ($flashSuccess): ?>
    <div class="mb-6 rounded-lg bg-green-100 text-green-700 px-4 py-3 text-sm">
        <?php echo htmlspecialchars($flashSuccess); ?>
    </div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="mb-6 rounded-lg bg-red-100 text-red-700 px-4 py-3 text-sm">
        <?php echo htmlspecialchars($flashError); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500 mb-1">Usuarios ativos</p>
        <p class="text-3xl font-semibold text-blue-700"><?php echo $totalUsers; ?></p>
        <p class="text-xs text-slate-400 mt-1">Contas comuns registradas.</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500 mb-1">Administradores</p>
        <p class="text-3xl font-semibold text-blue-700"><?php echo $totalAdmins; ?></p>
        <p class="text-xs text-slate-400 mt-1">Contas com acesso ao painel.</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500 mb-1">Novos (7 dias)</p>
        <p class="text-3xl font-semibold text-blue-700"><?php echo $usersLast7Days; ?></p>
        <p class="text-xs text-slate-400 mt-1">Cadastros nos ultimos 7 dias.</p>
    </div>
    <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-slate-500 mb-1">Creditos disponiveis</p>
        <p class="text-3xl font-semibold text-blue-700"><?php echo $sumCredits; ?></p>
        <p class="text-xs text-slate-400 mt-1">Total de creditos em contas de usuarios.</p>
    </div>
</div>

<div class="bg-white rounded-xl shadow">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-blue-700">Novos cadastros</h2>
            <p class="text-sm text-slate-500">Ultimos usuarios que entraram na plataforma.</p>
        </div>
        <a href="users.php" class="text-sm text-blue-600 hover:text-blue-700">Ver todos</a>
    </div>
    <?php if (empty($recentUsers)): ?>
        <p class="px-6 py-6 text-sm text-slate-500">Nenhum cadastro encontrado.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-blue-50 text-blue-700 text-left">
                        <th class="px-6 py-3 font-medium">Nome</th>
                        <th class="px-6 py-3 font-medium">E-mail</th>
                        <th class="px-6 py-3 font-medium">Creditos</th>
                        <th class="px-6 py-3 font-medium">Criado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $user): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-6 py-3 text-slate-700"><?php echo htmlspecialchars($user['name'] ?? '-'); ?></td>
                            <td class="px-6 py-3 text-slate-600"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td class="px-6 py-3 text-slate-600"><?php echo (int) ($user['credits'] ?? 0); ?></td>
                            <td class="px-6 py-3 text-slate-500"><?php echo $user['created_at'] ? date('d/m/Y H:i', strtotime($user['created_at'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow mt-8">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-blue-700">Ajustes de creditos</h2>
            <p class="text-sm text-slate-500">Historico recente de inclusoes ou remocoes manuais.</p>
        </div>
        <a href="users.php" class="text-sm text-blue-600 hover:text-blue-700">Gerenciar creditos</a>
    </div>
    <?php if (empty($recentAdjustments)): ?>
        <p class="px-6 py-6 text-sm text-slate-500">Nenhum ajuste registrado ate o momento.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-blue-50 text-blue-700 text-left">
                        <th class="px-6 py-3 font-medium">Usuario</th>
                        <th class="px-6 py-3 font-medium">Acao</th>
                        <th class="px-6 py-3 font-medium">Responsavel</th>
                        <th class="px-6 py-3 font-medium">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAdjustments as $adjustment): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-6 py-3 text-slate-700">
                                <div class="font-medium"><?php echo htmlspecialchars($adjustment['user_name'] ?? '-'); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($adjustment['user_email'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-3 text-slate-600">
                                <?php
                                    $direction = $adjustment['direction'] === 'add' ? 'Adicionados' : 'Removidos';
                                    $badgeColor = $adjustment['direction'] === 'add' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                                ?>
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold <?php echo $badgeColor; ?>">
                                    <?php echo $direction; ?>
                                    <span class="text-sm font-bold"><?php echo (int) ($adjustment['change_amount'] ?? 0); ?></span>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-slate-600">
                                <?php echo htmlspecialchars($adjustment['admin_name'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-3 text-slate-500">
                                <?php echo $adjustment['created_at'] ? date('d/m/Y H:i', strtotime($adjustment['created_at'])) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
renderAdminPageEnd();

