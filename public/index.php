<?php
/**
 * public/index.php
 *
 * Dashboard principal com visão de créditos e últimas exportações.
 */

use App\Auth;
use App\ExportService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ExportService.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$user = Auth::user();
$credits = $user['credits'] ?? 0;
$exports = ExportService::listarHistorico((int) $user['id']);
$recentExports = array_slice($exports, 0, 5);

renderPageStart('Dashboard', 'dashboard');
?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-sm text-slate-500 mb-2">Seus créditos</p>
            <p class="text-3xl font-semibold text-blue-700"><?php echo (int) $credits; ?></p>
            <p class="text-sm text-slate-400 mt-2">Cada lead detalhado consome <?php echo LEAD_VIEW_COST; ?> crédito.</p>
            <a href="comprar-creditos.php" class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg">
                Comprar créditos
            </a>
        </div>
        <div class="bg-white rounded-xl shadow p-6 md:col-span-2">
            <p class="text-sm text-slate-500 mb-2">Próximos passos</p>
            <ul class="space-y-3 text-slate-600 list-disc list-inside">
                <li>Buscar leads por segmento e quantidade desejada.</li>
                <li>Visualizar detalhes completos consumindo créditos.</li>
                <li>Exportar listas CSV e acessar o histórico de downloads.</li>
            </ul>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-blue-700">Últimas exportações</h2>
            <a href="historico-exportacoes.php" class="text-sm text-blue-600 hover:text-blue-700">Ver histórico completo</a>
        </div>

        <?php if (empty($recentExports)): ?>
            <p class="text-sm text-slate-500">Você ainda não realizou exportações.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-blue-50 text-blue-700 text-left">
                            <th class="px-4 py-2 font-medium">Data</th>
                            <th class="px-4 py-2 font-medium">Segmento</th>
                            <th class="px-4 py-2 font-medium">Quantidade</th>
                            <th class="px-4 py-2 font-medium">Arquivo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentExports as $export): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2 text-slate-600"><?php echo date('d/m/Y H:i', strtotime($export['created_at'])); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($export['segment'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo (int) ($export['quantity'] ?? 0); ?></td>
                            <td class="px-4 py-2">
                                <a href="../<?php echo htmlspecialchars($export['file_path']); ?>" class="text-blue-600 hover:text-blue-700" download>Baixar</a>
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
