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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'renomear') {
        $novoNome = trim($_POST['novo_nome'] ?? '');
        $listaPostId = (int) ($_POST['lista_id'] ?? 0);
        if ($listaPostId !== $listaId) {
            $_SESSION['flash_error'] = 'Lista invalida.';
        } elseif ($novoNome === '') {
            $_SESSION['flash_error'] = 'Informe um nome valido para renomear a lista.';
        } else {
            if (LeadListService::renomearLista($userId, $listaId, $novoNome)) {
                $_SESSION['flash_success'] = 'Nome da lista atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Nao foi possivel atualizar o nome da lista.';
            }
        }
        header('Location: lista-detalhe.php?id=' . $listaId);
        exit;
    }
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$flashDownload = $_SESSION['flash_export_download'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_export_download']);

$leads = LeadListService::listarItens($userId, $listaId);
$todasListas = LeadListService::listarListas($userId);

renderPageStart('Lista: ' . $lista['name'], 'listas');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-6">
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-semibold text-blue-700">Lista: <span id="lista-nome-atual"><?php echo htmlspecialchars($lista['name']); ?></span></h1>
                    <button type="button" class="rename-trigger inline-flex items-center justify-center w-8 h-8 rounded-full border border-slate-200 text-slate-500 hover:text-blue-700 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-600" data-target="rename-form-<?php echo (int) $listaId; ?>" aria-label="Editar nome da lista">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M13.586 3a2 2 0 0 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM12.172 4.414 4 12.586V16h3.414l8.172-8.172-3.414-3.414Z" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-slate-500">
                    Criada em <?php echo date('d/m/Y H:i', strtotime($lista['created_at'])); ?>
                </p>
                <p class="text-xs text-slate-400 mt-1">
                    Total de leads: <?php echo count($leads); ?>
                </p>
                <form id="rename-form-<?php echo (int) $listaId; ?>" method="post" class="hidden mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                    <input type="hidden" name="acao" value="renomear">
                    <input type="hidden" name="lista_id" value="<?php echo (int) $listaId; ?>">
                    <label for="novo-nome-lista" class="sr-only">Novo nome da lista</label>
                    <input id="novo-nome-lista" type="text" name="novo_nome" value="<?php echo htmlspecialchars($lista['name']); ?>" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Novo nome">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-2 rounded-lg text-sm">
                        Salvar
                    </button>
                </form>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full md:w-auto">
                <?php if (!empty($leads)): ?>
                    <form method="post" action="exportar-lista.php" class="flex items-center gap-3">
                        <input type="hidden" name="lista_id" value="<?php echo (int) $listaId; ?>">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                            Exportar lista
                        </button>
                    </form>
                <?php endif; ?>
            </div>
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
                            <th class="px-4 py-2 font-medium">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <?php
                            $itemId = (int) ($lead['item_id'] ?? 0);
                            $outrasListas = array_filter($todasListas, static function ($registroLista) use ($listaId) {
                                return (int) ($registroLista['id'] ?? 0) !== $listaId;
                            });
                        ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['empresa'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['segmento'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cnpj_formatado'] ?? ($lead['cnpj'] ?? '-')); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['email'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['telefone'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['cidade'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['uf'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($lead['situacao'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600">
                                <?php if (!empty($outrasListas) && $itemId > 0): ?>
                                    <form method="post" action="mover-lead-lista.php" class="space-y-2">
                                        <input type="hidden" name="item_id" value="<?php echo $itemId; ?>">
                                        <input type="hidden" name="redirect" value="lista-detalhe.php?id=<?php echo (int) $listaId; ?>">
                                        <label class="block text-xs font-medium text-slate-500">Mover para</label>
                                        <select name="lista_destino" class="w-full border border-slate-200 rounded-lg px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-blue-600" required>
                                            <option value="">Selecione</option>
                                            <?php foreach ($outrasListas as $listaDestino): ?>
                                                <option value="<?php echo (int) $listaDestino['id']; ?>">
                                                    <?php echo htmlspecialchars($listaDestino['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-3 py-2 rounded-lg">
                                            Mover
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Nenhuma outra lista disponivel.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr class="border-b border-slate-100 bg-slate-50">
                            <td colspan="9" class="px-4 py-2 text-xs text-slate-500">
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var triggers = document.querySelectorAll('.rename-trigger');
            triggers.forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    var targetId = trigger.getAttribute('data-target');
                    if (!targetId) {
                        return;
                    }
                    var form = document.getElementById(targetId);
                    if (!form) {
                        return;
                    }
                    var isHidden = form.classList.contains('hidden');
                    if (isHidden) {
                        form.classList.remove('hidden');
                        var input = form.querySelector('input[name="novo_nome"]');
                        if (input) {
                            setTimeout(function () {
                                input.focus();
                                input.select();
                            }, 0);
                        }
                    } else {
                        form.classList.add('hidden');
                    }
                });
            });
        });
    </script>
<?php
renderPageEnd();
