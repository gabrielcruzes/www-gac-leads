<?php
/**
 * public/comprar-creditos.php
 *
 * Página de compra de créditos com geração de PIX via Asaas.
 */

use App\Auth;
use App\Payments\PaymentPlans;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Payments/PaymentPlans.php';
require_once __DIR__ . '/components.php';

Auth::requireLogin();

$user = Auth::user();
$saldo = $user['credits'] ?? 0;
$planos = PaymentPlans::all();
$nome = $user['name'] ?? '';
$email = $user['email'] ?? '';

renderPageStart('Comprar Créditos', 'creditos');
?>
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h1 class="text-xl font-semibold text-blue-700 mb-2">Planos de créditos</h1>
        <p class="text-sm text-slate-500">Selecione um plano, informe seus dados e gere um QR Code PIX instantâneo.</p>
        <p class="text-sm text-slate-500 mt-2">Saldo atual: <span class="font-semibold text-blue-700"><?php echo (int) $saldo; ?> créditos</span></p>
    </div>

    <div id="paymentAlert" class="hidden mb-6 rounded-lg px-4 py-3 text-sm"></div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold text-blue-700 mb-4">Dados do comprador</h2>
            <div class="space-y-4">
                <div>
                    <label for="customerName" class="block text-sm font-medium text-slate-600 mb-1">Nome completo</label>
                    <input id="customerName" type="text" value="<?php echo htmlspecialchars($nome); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Nome do responsável" required>
                </div>
                <div>
                    <label for="customerEmail" class="block text-sm font-medium text-slate-600 mb-1">E-mail</label>
                    <input id="customerEmail" type="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="email@empresa.com" required>
                </div>
                <div>
                    <label for="customerCpf" class="block text-sm font-medium text-slate-600 mb-1">CPF ou CNPJ</label>
                    <input id="customerCpf" type="text" inputmode="numeric" maxlength="18" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="Somente números" required>
                </div>
                <div>
                    <label for="customerPhone" class="block text-sm font-medium text-slate-600 mb-1">Telefone (opcional)</label>
                    <input id="customerPhone" type="text" inputmode="tel" maxlength="15" class="w-full rounded-lg border border-slate-200 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" placeholder="DDD + número">
                </div>
                <p class="text-xs text-slate-500">Os dados acima são enviados para o Asaas apenas para identificação fiscal do pagamento.</p>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="text-lg font-semibold text-blue-700 mb-4">Selecione um plano</h2>
            <div class="grid gap-4">
                <?php foreach ($planos as $tipo => $plano): ?>
                    <div class="rounded-xl border border-slate-200 p-5 flex flex-col gap-3">
                        <div>
                            <p class="text-sm text-slate-500 uppercase tracking-wide"><?php echo htmlspecialchars($plano['name']); ?></p>
                            <p class="text-2xl font-semibold text-blue-700">R$ <?php echo number_format($plano['amount'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-slate-500"><?php echo number_format($plano['credits'], 0, ',', '.'); ?> créditos</p>
                        </div>
                        <button
                            type="button"
                            class="generatePixButton inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-white font-semibold hover:bg-blue-700 transition"
                            data-plan="<?php echo htmlspecialchars($tipo); ?>"
                        >
                            Gerar QR Code PIX
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="pixResult" class="hidden mt-8 bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-blue-700">Pagamento gerado</h2>
                <p class="text-sm text-slate-500">Escaneie o QR Code abaixo ou copie o código PIX.</p>
            </div>
            <span id="pixStatusBadge" class="text-xs font-semibold uppercase px-3 py-1 rounded-full bg-yellow-100 text-yellow-700">PENDENTE</span>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="flex flex-col items-center justify-center">
                <img id="pixQrImage" src="" alt="QR Code PIX" class="w-56 h-56 object-contain border border-slate-100 rounded-lg mb-3 hidden">
                <p class="text-sm text-slate-500 text-center">Valor: <span id="pixAmount" class="font-semibold text-blue-700"></span></p>
                <p class="text-sm text-slate-500 text-center">Créditos: <span id="pixCredits" class="font-semibold text-blue-700"></span></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">Código copia e cola</label>
                <textarea id="pixPayload" class="w-full h-32 rounded-lg border border-slate-200 px-3 py-2 text-sm" readonly></textarea>
                <button id="copyPixPayload" type="button" class="mt-3 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    Copiar código
                </button>
                <p class="text-xs text-slate-500 mt-3">Expira em: <span id="pixExpiration" class="font-semibold text-slate-700"></span></p>
                <p class="text-xs text-slate-500">Após o pagamento ser confirmado iremos adicionar os créditos automaticamente.</p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const alertBox = document.getElementById('paymentAlert');
            const buttons = document.querySelectorAll('.generatePixButton');
            const nameInput = document.getElementById('customerName');
            const emailInput = document.getElementById('customerEmail');
            const cpfInput = document.getElementById('customerCpf');
            const phoneInput = document.getElementById('customerPhone');
            const pixResult = document.getElementById('pixResult');
            const pixQr = document.getElementById('pixQrImage');
            const pixPayload = document.getElementById('pixPayload');
            const pixExpiration = document.getElementById('pixExpiration');
            const pixAmount = document.getElementById('pixAmount');
            const pixCredits = document.getElementById('pixCredits');
            const pixStatusBadge = document.getElementById('pixStatusBadge');
            const copyButton = document.getElementById('copyPixPayload');
            let pollInterval = null;
            let currentTransactionId = null;

            function showAlert(type, message) {
                if (!alertBox) return;
                alertBox.textContent = message;
                alertBox.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
                if (type === 'error') {
                    alertBox.classList.add('bg-red-100', 'text-red-700');
                } else {
                    alertBox.classList.add('bg-green-100', 'text-green-700');
                }
            }

            function setButtonsDisabled(disabled) {
                buttons.forEach(function (button) {
                    button.disabled = disabled;
                    button.classList.toggle('opacity-50', disabled);
                });
            }

            function formatDate(value) {
                if (!value) return '---';
                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }
                return date.toLocaleString('pt-BR');
            }

            function renderPix(payment) {
                if (!payment) {
                    return;
                }
                pixResult.classList.remove('hidden');
                pixPayload.value = payment.pix?.payload || '';
                pixAmount.textContent = `R$ ${Number(payment.value).toFixed(2).replace('.', ',')}`;
                pixCredits.textContent = payment.credits || 0;
                pixExpiration.textContent = formatDate(payment.pix?.expirationDate);
                pixStatusBadge.textContent = payment.status;
                pixStatusBadge.className = 'text-xs font-semibold uppercase px-3 py-1 rounded-full ' + (payment.status === 'PAID' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700');
                if (payment.pix?.qrcode) {
                    pixQr.src = payment.pix.qrcode;
                    pixQr.classList.remove('hidden');
                }
            }

            async function pollStatus(transactionId) {
                if (!transactionId) {
                    return;
                }
                clearInterval(pollInterval);
                pollInterval = setInterval(async function () {
                    try {
                        const response = await fetch(`/api/payments/status.php?id=${transactionId}`);
                        const data = await response.json();
                        if (data?.payment) {
                            renderPix(data.payment);
                            if (data.payment.status === 'PAID') {
                                clearInterval(pollInterval);
                                showAlert('success', 'Pagamento confirmado! Créditos adicionados em instantes.');
                                setTimeout(function () { window.location.reload(); }, 2500);
                            }
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }, 8000);
            }

            async function handleGenerate(plan) {
                const name = nameInput.value.trim();
                const email = emailInput.value.trim();
                const cpf = cpfInput.value.replace(/\D+/g, '');
                const phone = phoneInput.value.replace(/\D+/g, '');

                if (!name || !email || !cpf) {
                    showAlert('error', 'Informe nome, e-mail e CPF/CNPJ para gerar o PIX.');
                    return;
                }

                setButtonsDisabled(true);
                showAlert('success', 'Gerando QR Code PIX...');

                try {
                    const response = await fetch('/api/payments/create.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            planType: plan,
                            customer: {
                                name,
                                email,
                                cpfCnpj: cpf,
                                mobilePhone: phone,
                            },
                        }),
                    });

                    const data = await response.json();
                    if (!response.ok || !data?.success) {
                        throw new Error(data?.error || 'Não foi possível gerar o PIX.');
                    }

                    currentTransactionId = data.payment.id;
                    renderPix(data.payment);
                    pollStatus(currentTransactionId);
                    showAlert('success', 'Pagamento criado! Complete o PIX dentro do prazo.');
                } catch (error) {
                    console.error(error);
                    showAlert('error', error.message);
                } finally {
                    setButtonsDisabled(false);
                }
            }

            buttons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const plan = button.dataset.plan;
                    handleGenerate(plan);
                });
            });

            copyButton.addEventListener('click', function () {
                if (!pixPayload.value) {
                    return;
                }
                navigator.clipboard.writeText(pixPayload.value).then(function () {
                    copyButton.textContent = 'Copiado!';
                    setTimeout(function () {
                        copyButton.textContent = 'Copiar código';
                    }, 2000);
                });
            });
        })();
    </script>
<?php
renderPageEnd();
