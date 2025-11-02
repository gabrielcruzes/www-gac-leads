<?php
/**
 * public/historico-exportacoes.php
 *
 * Lista todas as exportações realizadas pelo usuário.
 */

use App\Auth;
use App\ExportService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/ExportService.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$user = Auth::user();
$exports = ExportService::listarHistorico((int) $user['id']);

renderPageStart('Histórico de Exportações', 'historico');
?>
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-semibold text-blue-700">Histórico de exportações</h1>
            <a href="buscar-leads.php" class="text-sm text-blue-600 hover:text-blue-700">Nova exportação</a>
        </div>

        <?php if (empty($exports)): ?>
            <p class="text-sm text-slate-500">Você ainda não gerou exportações.</p>
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
                    <?php foreach ($exports as $export): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2 text-slate-600"><?php echo date('d/m/Y H:i', strtotime($export['created_at'])); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo htmlspecialchars($export['segment'] ?? '-'); ?></td>
                            <td class="px-4 py-2 text-slate-600"><?php echo (int) ($export['quantity'] ?? 0); ?></td>
                            <td class="px-4 py-2">
                                <a href="../<?php echo htmlspecialchars($export['file_path']); ?>" class="text-blue-600 hover:text-blue-700" download>
                                    Baixar CSV
                                </a>
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
