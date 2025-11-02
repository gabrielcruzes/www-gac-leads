<?php
/**
 * public/comprar-creditos.php
 *
 * Página para compra simulada de créditos com planos pré-definidos.
 */

use App\Auth;
use App\CreditService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/CreditService.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $credits = (int) ($_POST['credits'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);

    if ($credits <= 0 || $amount <= 0) {
        $erro = 'Plano inválido.';
    } else {
        $user = Auth::user();
        if ($user && CreditService::comprarCreditosSimples((int) $user['id'], $credits, $amount)) {
            $mensagem = sprintf('Compra confirmada! %d créditos adicionados à sua conta.', $credits);
        } else {
            $erro = 'Não foi possível completar a compra. Tente novamente.';
        }
    }
}

$userAtualizado = Auth::user();
$saldo = $userAtualizado['credits'] ?? 0;
//TODO Pagamento Integrado
$planos = [
    ['credits' => 0, 'amount' => 9.90],
    ['credits' => 0, 'amount' => 39.90],
    ['credits' => 0, 'amount' => 69.90],
];

renderPageStart('Comprar Créditos', 'creditos');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h1 class="text-xl font-semibold text-blue-700 mb-2">Planos de créditos</h1>
        <p class="text-sm text-slate-500">Simule a compra dos créditos necessários para visualizar leads.</p>
        <p class="text-sm text-slate-500 mt-2">Saldo atual: <span class="font-semibold text-blue-700"><?php echo (int) $saldo; ?> créditos</span></p>
    </div>

    <?php if ($mensagem): ?>
        <div class="mb-6 rounded-lg bg-green-100 text-green-700 px-4 py-3">
            <?php echo htmlspecialchars($mensagem); ?>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="mb-6 rounded-lg bg-red-100 text-red-700 px-4 py-3">
            <?php echo htmlspecialchars($erro); ?>
        </div>
    <?php endif; ?>

    <div class="grid md:grid-cols-3 gap-6">
        <?php foreach ($planos as $plano): ?>
            <form method="post" class="bg-blue-600 text-white rounded-xl shadow-lg p-6 flex flex-col justify-between">
                <div>
                    <h2 class="text-lg font-semibold mb-2"><?php echo $plano['credits']; ?> créditos</h2>
                    <p class="text-3xl font-bold mb-4">R$ <?php echo number_format($plano['amount'], 2, ',', '.'); ?></p>
                    <p class="text-sm text-blue-100 mb-4">Ideal para <?php echo $plano['credits'] / LEAD_VIEW_COST; ?> visualizações detalhadas.</p>
                </div>
                <div>
                    <input type="hidden" name="credits" value="<?php echo $plano['credits']; ?>">
                    <input type="hidden" name="amount" value="<?php echo $plano['amount']; ?>">
                    <button type="submit" class="w-full bg-white text-blue-700 font-semibold py-2 rounded-lg hover:bg-blue-50 transition">
                        Comprar agora
                    </button>
                    <p class="text-xs text-blue-100 mt-3">// TODO integrar gateway de pagamento real.</p>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
<?php
renderPageEnd();
