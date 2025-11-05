<?php
/**
 * public/exportar-csv.php
 *
 * Gera arquivos CSV a partir da ultima busca de leads realizada.
 */

use App\Auth;
use App\ExportService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ExportService.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$successMessage = '';
$errorMessage = '';
$downloadLink = '';
$segmentoExportado = '';
$quantidadeExportada = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['last_export_ready']);
    $token = $_POST['export_token'] ?? '';
    $lastToken = $_SESSION['last_search_token'] ?? '';
    $dadosBusca = $_SESSION['last_search_export'] ?? null;

    if (!$token || !$dadosBusca || $token !== $lastToken) {
        $errorMessage = 'Nao foi possivel identificar os dados para exportacao. Realize uma nova busca.';
    } else {
        $segmento = $dadosBusca['segmento'] ?? 'Indefinido';
        $leads = $dadosBusca['leads'] ?? [];

        // Normaliza os dados removendo chaves de controle internas.
        $leadsParaExportar = array_map(function ($lead) {
            if (isset($lead['token'])) {
                unset($lead['token']);
            }
            return $lead;
        }, $leads);

        if (empty($leadsParaExportar)) {
            $errorMessage = 'Nao ha registros suficientes para gerar o CSV. Realize uma nova busca com resultados.';
            unset($_SESSION['last_export_ready']);
        } else {
            $user = Auth::user();
            $filePath = ExportService::gerarCsv((int) $user['id'], $segmento, $leadsParaExportar);

            if ($filePath) {
                $successMessage = 'Exportacao gerada com sucesso! Seu arquivo esta pronto para download.';
                $downloadLink = 'download-export.php?token=' . urlencode($token);
                $segmentoExportado = $segmento;
                $quantidadeExportada = count($leadsParaExportar);
                $_SESSION['last_export_ready'] = [
                    'token' => $token,
                    'path' => $filePath,
                    'filename' => basename($filePath),
                ];
            } else {
                $errorMessage = 'Falha ao gerar o arquivo CSV. Tente novamente.';
                unset($_SESSION['last_export_ready']);
            }
        }
    }
}

renderPageStart('Exportar CSV', 'buscar');
?>
    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-xl font-semibold text-blue-700 mb-4">Exportacao de leads</h1>

        <?php if ($successMessage): ?>
            <div class="rounded-lg bg-green-100 text-green-700 px-4 py-3 mb-4">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
            <p class="text-sm text-slate-500 mb-3">
                Segmento: <strong><?php echo htmlspecialchars($segmentoExportado); ?></strong> |
                Leads exportados: <strong><?php echo (int) $quantidadeExportada; ?></strong>
            </p>
            <a href="<?php echo htmlspecialchars($downloadLink); ?>" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg" download>
                Baixar CSV
            </a>
            <p class="text-xs text-slate-400 mt-3">O arquivo tambem foi adicionado ao historico de exportacoes.</p>
        <?php elseif ($errorMessage): ?>
            <div class="rounded-lg bg-red-100 text-red-700 px-4 py-3 mb-4">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
            <a href="buscar-leads.php" class="inline-block text-blue-600 hover:text-blue-700 font-medium">
                Voltar para buscar leads
            </a>
        <?php else: ?>
            <p class="text-sm text-slate-500">Nenhuma exportacao solicitada. Realize uma busca de leads e, em seguida, exporte os resultados.</p>
            <a href="buscar-leads.php" class="inline-block text-blue-600 hover:text-blue-700 font-medium mt-3">
                Ir para buscar leads
            </a>
        <?php endif; ?>
    </div>
<?php
renderPageEnd();
