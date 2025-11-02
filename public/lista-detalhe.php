<?php
/**
 * public/lista-detalhe.php
 *
 * Exibe os leads pertencentes a uma lista especifica.
 */

use App\Auth;
use App\LeadListService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LeadListService.php';
require_once __DIR__ . '/partials/lead-details.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$user = Auth::user();
$userId = (int) ($user['id'] ?? 0);
$listaId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($listaId <= 0) {
    $_SESSION['flash_error'] = 'Lista invalida.';
    header('Location: listas.php');
    exit;
}

$lista = LeadListService::obterLista($userId, $listaId);
if (!$lista) {
    $_SESSION['flash_error'] = 'Lista nao encontrada.';
    header('Location: listas.php');
    exit;
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$flashDownload = $_SESSION['flash_export_download'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_export_download']);

$leads = LeadListService::listarItens($userId, $listaId);

renderPageStart('Lista: ' . $lista['name'], 'listas');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-semibold text-blue-700">Lista: <?php echo htmlspecialchars($lista['name']); ?></h1>
                <p class="text-sm text-slate-500">
                    Criada em <?php echo date('d/m/Y H:i', strtotime($lista['created_at'])); ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">
                    Total de leads: <?php echo count($leads); ?>
                </p>
            </div>
            <?php if (!empty($leads)): ?>
                <form method="post" action="exportar-lista.php" class="flex items-center gap-3">
                    <input type="hidden" name="lista_id" value="<?php echo (int) $listaId; ?>">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                        Exportar lista
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="rounded-lg bg-green-100 text-green-700 px-4 py-3 mb-4 text-sm space-y-2">
                <p><?php echo htmlspecialchars($flashSuccess); ?></p>
                <?php if ($flashDownload): ?>
                    <a href="<?php echo htmlspecialchars($flashDownload); ?>" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-2 rounded-lg" download>
                        Baixar CSV
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mb-4 text-sm">
                <?php echo htmlspecialchars($flashError); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($leads)): ?>
            <p class="text-sm text-slate-500">Nenhum lead foi adicionado ainda. Volte a <a href="buscar-leads.php" class="text-blue-600 hover:text-blue-700 font-medium">buscar leads</a> e envie para esta lista.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-blue-50 text-blue-700 text-left">
                            <th class="px-4 py-2 font-medium">Empresa</th>
                            <th class="px-4 py-2 font-medium">Segmento</th>
                            <th class="px-4 py-2 font-medium">CNPJ</th>
                            <th class="px-4 py-2 font-medium">E-mail</th>
                            <th class="px-4 py-2 font-medium">Telefone</th>
                            <th class="px-4 py-2 font-medium">Municipio</th>
                            <th class="px-4 py-2 font-medium">UF</th>
                            <th class="px-4 py-2 font-medium">Situacao</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['empresa'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['segmento'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cnpj_formatado'] ?? ($lead['cnpj'] ?? '-')); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['email'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['telefone'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cidade'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['uf'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['situacao'] ?? '-'); ?></td>
                        </tr>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <td colspan="8" class="px-4 py-2 text-xs text-slate-500">
                                <details>
                                    <summary class="cursor-pointer text-blue-600 hover:text-blue-700">Ver detalhes completos</summary>
                                    <div class="mt-3 space-y-4">
                                        <?php renderLeadDetails($lead['detalhes'] ?? [], $lead); ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php
renderPageEnd();
