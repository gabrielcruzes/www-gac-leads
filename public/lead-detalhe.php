<?php
/**
 * public/lead-detalhe.php
 *
 * Exibe detalhes completos do lead, consumindo creditos quando necessario.
 */

use App\Auth;
use App\LeadService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/LeadService.php';
require_once __DIR__ . '/partials/lead-details.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$leadId = $_GET['id'] ?? '';

$resultado = null;
if ($leadId) {
    $resultado = LeadService::visualizarLead($leadId);
}

renderPageStart('Detalhe do Lead', 'buscar');
?>
    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-semibold text-blue-700 mb-4">Detalhes do lead</h1>

        <?php if (!$leadId): ?>
            <p class="text-sm text-slate-500">Nenhum lead selecionado.</p>
        <?php elseif (!$resultado): ?>
            <p class="text-sm text-slate-500">Lead nao encontrado ou nao disponível.</p>
        <?php elseif (isset($resultado['error'])): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mb-4">
                <?php echo htmlspecialchars($resultado['error']); ?>
            </div>
            <a href="comprar-creditos.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                Comprar creditos
            </a>
        <?php else: ?>
            <?php
            $summary = $resultado['summary'] ?? [];
            $dados = $resultado['data'] ?? [];
            $empresa = $summary['empresa'] ?? ($dados['razao_social'] ?? 'Empresa nao informada');
            $cnpj = $summary['cnpj_formatado'] ?? ($summary['cnpj'] ?? ($dados['cnpj'] ?? '-'));
            $situacaoResumo = $dados['situacao_cadastral']['situacao_atual'] ?? '-';
            ?>

            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-blue-600 mb-1">Empresa</p>
                    <h2 class="text-lg font-semibold text-blue-800 mb-2">
                        <?php echo htmlspecialchars($empresa); ?>
                    </h2>
                    <p class="text-sm text-blue-700">CNPJ: <?php echo htmlspecialchars($cnpj); ?></p>
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Creditos restantes</p>
                    <p class="text-3xl font-semibold text-blue-700"><?php echo (int) ($resultado['credits'] ?? 0); ?></p>
                    <p class="text-xs text-slate-500 mt-2">
                        Situação cadastral: <span class="font-medium text-slate-700"><?php echo htmlspecialchars(strtoupper($situacaoResumo)); ?></span>
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                <?php renderLeadDetails($dados, $summary); ?>
            </div>
        <?php endif; ?>
    </div>
<?php
renderPageEnd();
