<?php
/**
 * public/admin/users.php
 *
 * Listagem e gerenciamento de usuarios.
 */

use App\Auth;
use App\Database;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/components.php';

Auth::requireAdmin();

$pdo = Database::getConnection();

$query = trim($_GET['q'] ?? '');

if ($query !== '') {
    $searchStmt = $pdo->prepare("
        SELECT id, name, email, credits, role, created_at
        FROM users
        WHERE name LIKE :term OR email LIKE :term
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $searchStmt->execute([':term' => '%' . $query . '%']);
    $users = $searchStmt->fetchAll() ?: [];
} else {
    $listStmt = $pdo->query("
        SELECT id, name, email, credits, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $users = $listStmt->fetchAll() ?: [];
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

renderAdminPageStart('Usuarios', 'users');
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

<div class="bg-white rounded-xl shadow mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h1 class="text-xl font-semibold text-blue-700">Gerenciar usuarios</h1>
        <p class="text-sm text-slate-500 mt-1">Acompanhe cadastros, creditos e ajuste saldos quando necessario.</p>
    </div>
    <div class="px-6 py-4 border-b border-slate-100">
        <form class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <label class="flex-1">
                <span class="sr-only">Buscar por nome ou e-mail</span>
                <input
                    type="search"
                    name="q"
                    value="<?php echo htmlspecialchars($query); ?>"
                    placeholder="Buscar por nome ou e-mail..."
                    class="w-full rounded-lg border border-slate-200 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600"
                >
            </label>
            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Buscar
                </button>
                <?php if ($query !== ''): ?>
                    <a href="users.php" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                        Limpar
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php if (empty($users)): ?>
        <p class="px-6 py-6 text-sm text-slate-500">Nenhum usuario encontrado.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-blue-50 text-blue-700 text-left">
                        <th class="px-6 py-3 font-medium">Nome</th>
                        <th class="px-6 py-3 font-medium">E-mail</th>
                        <th class="px-6 py-3 font-medium">Perfil</th>
                        <th class="px-6 py-3 font-medium">Creditos</th>
                        <th class="px-6 py-3 font-medium">Criado em</th>
                        <th class="px-6 py-3 font-medium">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-6 py-3 text-slate-700 w-48"><?php echo htmlspecialchars($user['name'] ?? '-'); ?></td>
                            <td class="px-6 py-3 text-slate-600 w-64"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center rounded-full border border-slate-200 px-3 py-1 text-xs font-medium text-slate-600">
                                    <?php echo $user['role'] === 'admin' ? 'Admin' : 'Usuario'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 text-slate-700"><?php echo (int) ($user['credits'] ?? 0); ?></td>
                            <td class="px-6 py-3 text-slate-500 w-40">
                                <?php echo $user['created_at'] ? date('d/m/Y H:i', strtotime($user['created_at'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-3 w-80">
                                <div class="flex flex-col gap-4">
                                    <?php if ((int) $user['id'] === (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <p class="text-xs text-slate-400">Use outra conta admin para ajustar seu proprio saldo.</p>
                                    <?php else: ?>
                                        <form method="post" action="update-credits.php" class="flex flex-col gap-3 md:flex-row md:items-center">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                            <label class="flex items-center gap-2">
                                                <span class="text-xs text-slate-500 uppercase tracking-wide">Quantidade</span>
                                                <input
                                                    type="number"
                                                    name="amount"
                                                    min="1"
                                                    required
                                                    class="w-24 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600"
                                                >
                                            </label>
                                            <div class="flex gap-2">
                                                <button
                                                    type="submit"
                                                    name="action"
                                                    value="add"
                                                    class="rounded-lg bg-green-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-green-700"
                                                >
                                                    Adicionar
                                                </button>
                                                <button
                                                    type="submit"
                                                    name="action"
                                                    value="subtract"
                                                    class="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white hover:bg-red-700"
                                                >
                                                    Remover
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int) $user['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <form method="post" action="update-role.php" class="flex flex-col gap-2 md:flex-row md:items-center">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <input type="hidden" name="role" value="user">
                                                <button
                                                    type="submit"
                                                    class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-50 md:w-auto"
                                                >
                                                    Tornar usuario padrao
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="role" value="admin">
                                                <button
                                                    type="submit"
                                                    class="rounded-lg border border-blue-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-blue-700 hover:bg-blue-50 md:w-auto"
                                                >
                                                    Promover a admin
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="px-6 py-4 text-xs text-slate-400 border-t border-slate-100">
            Exibindo ate 100 registros. Utilize a busca para localizar usuarios especificos.
        </p>
    <?php endif; ?>
</div>

<?php
renderAdminPageEnd();

