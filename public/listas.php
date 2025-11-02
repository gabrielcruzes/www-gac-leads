<?php
/**
 * public/listas.php
 *
 * Gerencia as listas de leads do usuario.
 */

use App\Auth;
use App\LeadListService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LeadListService.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_lista'] ?? '');

    if ($nome === '') {
        $_SESSION['flash_error'] = 'Informe um nome para a lista.';
    } else {
        $novoId = LeadListService::criarLista($userId, $nome);
        if ($novoId) {
            $_SESSION['flash_success'] = 'Lista criada com sucesso.';
        } else {
            $_SESSION['flash_error'] = 'Nao foi possivel criar a lista.';
        }
    }

    header('Location: listas.php');
    exit;
}

$listas = LeadListService::listarListas($userId);

renderPageStart('Listas de Leads', 'listas');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
        <h1 class="text-xl font-semibold text-blue-700 mb-4">Suas listas de leads</h1>
        <p class="text-sm text-slate-500 mb-6">
            Organize seus leads em listas antes de exportar ou compartilhar.
        </p>

        <?php if ($flashSuccess): ?>
            <div class="rounded-lg bg-green-100 text-green-700 px-4 py-3 mb-4 text-sm">
                <?php echo htmlspecialchars($flashSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mb-4 text-sm">
                <?php echo htmlspecialchars($flashError); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="grid md:grid-cols-3 gap-4 items-end mb-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-600 mb-1">Nome da nova lista</label>
                <input type="text" name="nome_lista" class="w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: Leads de Tecnologia - Sao Paulo">
            </div>
            <div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                    Criar lista
                </button>
            </div>
        </form>

        <?php if (empty($listas)): ?>
            <p class="text-sm text-slate-500">Nenhuma lista criada ainda. Utilize o formul&aacute;rio acima para criar a primeira.</p>
        <?php else: ?>
            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($listas as $lista): ?>
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-lg font-semibold text-blue-700">
                                <?php echo htmlspecialchars($lista['name']); ?>
                            </h2>
                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-medium">
                                <?php echo (int) ($lista['total_items'] ?? 0); ?> leads
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            Criada em <?php echo date('d/m/Y H:i', strtotime($lista['created_at'])); ?>
                        </p>
                        <a href="lista-detalhe.php?id=<?php echo (int) $lista['id']; ?>" class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-medium">
                            Ver detalhes
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php
renderPageEnd();
