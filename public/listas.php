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
    $acao = $_POST['acao'] ?? 'criar';

    if ($acao === 'renomear') {
        $listaId = (int) ($_POST['lista_id'] ?? 0);
        $novoNome = trim($_POST['novo_nome'] ?? '');

        if ($listaId <= 0 || $novoNome === '') {
            $_SESSION['flash_error'] = 'Informe um nome valido para renomear a lista.';
        } else {
            if (LeadListService::renomearLista($userId, $listaId, $novoNome)) {
                $_SESSION['flash_success'] = 'Nome da lista atualizado com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Nao foi possivel atualizar o nome da lista.';
            }
        }
    } elseif ($acao === 'excluir') {
        $listaId = (int) ($_POST['lista_id'] ?? 0);

        if ($listaId <= 0) {
            $_SESSION['flash_error'] = 'Lista invalida para exclusao.';
        } elseif (LeadListService::removerLista($userId, $listaId)) {
            $_SESSION['flash_success'] = 'Lista removida e leads movidos para "' . LeadListService::DEFAULT_LIST_NAME . '".';
        } else {
            $_SESSION['flash_error'] = 'Nao foi possivel remover a lista selecionada.';
        }
    } else {
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
    }

    header('Location: listas.php');
    exit;
}

$listas = LeadListService::listarListas($userId);
$listaPadrao = LeadListService::obterOuCriarListaPadrao($userId);
$defaultListId = $listaPadrao ? (int) ($listaPadrao['id'] ?? 0) : 0;

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
            <input type="hidden" name="acao" value="criar">
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
                    <?php $isDefaultList = $defaultListId > 0 && (int) $lista['id'] === $defaultListId; ?>
                    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-blue-700">
                                    <?php echo htmlspecialchars($lista['name']); ?>
                                </h2>
                                <button type="button" class="rename-trigger inline-flex items-center justify-center w-8 h-8 rounded-full border border-slate-200 text-slate-500 hover:text-blue-700 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-600" data-target="rename-form-<?php echo (int) $lista['id']; ?>" aria-label="Editar nome da lista <?php echo htmlspecialchars($lista['name']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M13.586 3a2 2 0 0 1 2.828 2.828l-.793.793-2.828-2.828.793-.793ZM12.172 4.414 4 12.586V16h3.414l8.172-8.172-3.414-3.414Z" />
                                    </svg>
                                </button>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 text-blue-700 font-medium whitespace-nowrap">
                                <?php echo (int) ($lista['total_items'] ?? 0); ?> leads
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">
                            Criada em <?php echo date('d/m/Y H:i', strtotime($lista['created_at'])); ?>
                        </p>
                        <form id="rename-form-<?php echo (int) $lista['id']; ?>" method="post" class="hidden flex flex-col sm:flex-row sm:items-center gap-2 mb-3">
                            <input type="hidden" name="acao" value="renomear">
                            <input type="hidden" name="lista_id" value="<?php echo (int) $lista['id']; ?>">
                            <input type="text" name="novo_nome" value="<?php echo htmlspecialchars($lista['name']); ?>" class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Novo nome da lista">
                            <button type="submit" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-medium px-3 py-2 rounded-lg text-sm">
                                Salvar nome
                            </button>
                        </form>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="lista-detalhe.php?id=<?php echo (int) $lista['id']; ?>" class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-medium">
                                Ver detalhes
                            </a>
                            <?php if (!$isDefaultList): ?>
                                <form method="post" onsubmit="return confirm('Tem certeza de que deseja excluir esta lista? Os leads serao movidos para <?php echo LeadListService::DEFAULT_LIST_NAME; ?>.');">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="lista_id" value="<?php echo (int) $lista['id']; ?>">
                                    <button type="submit" class="inline-flex items-center gap-2 text-sm text-red-600 hover:text-red-700 font-medium">
                                        Excluir lista
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Leads sem lista permanecem aqui.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
