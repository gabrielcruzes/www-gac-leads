<?php
/**
 * Pagina exemplo demonstrando o consumo do endpoint api_cnaes.php.
 *
 * Pode ser acessada sem autenticacao para testes rapidos.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busca CNAE (Demo)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg bg-white rounded-xl shadow p-6 space-y-4">
        <header>
            <h1 class="text-2xl font-semibold text-blue-700">Buscar CNAE</h1>
            <p class="text-sm text-slate-500">Digite ao menos 3 caracteres para pesquisar por codigo ou descricao.</p>
        </header>

        <div class="space-y-3">
            <label class="block text-sm font-medium text-slate-600">
                CNAE ou descricao
                <input id="cnae-input" type="text" class="mt-1 w-full border border-slate-200 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" placeholder="Ex.: 6201 ou software">
            </label>
            <input id="cnae-selecionado" type="hidden" name="cnae">
        </div>

        <ul id="resultado-lista" class="divide-y divide-slate-200 border border-slate-200 rounded-lg bg-slate-50"></ul>

        <footer class="text-xs text-slate-400">
            Resultado escolhido sera enviado para o input oculto <code>cnae</code>.
        </footer>
    </div>

    <script>
        const inputBusca = document.getElementById('cnae-input');
        const inputSelecionado = document.getElementById('cnae-selecionado');
        const listaResultados = document.getElementById('resultado-lista');
        let ultimoTermo = '';

        function renderizarResultados(items) {
            listaResultados.innerHTML = '';
            if (!items.length) {
                const vazio = document.createElement('li');
                vazio.className = 'px-4 py-3 text-sm text-slate-500';
                vazio.textContent = 'Nenhum CNAE encontrado.';
                listaResultados.appendChild(vazio);
                return;
            }

            items.forEach(function (item) {
                const li = document.createElement('li');
                li.className = 'px-4 py-3 hover:bg-blue-50 cursor-pointer transition-colors';
                li.innerHTML = '<span class="font-semibold text-blue-700">' + item.codigo + '</span><br><span class="text-sm text-slate-600">' + item.descricao + '</span>';
                li.addEventListener('click', function () {
                    inputBusca.value = item.codigo + ' - ' + item.descricao;
                    inputSelecionado.value = item.codigo;
                    renderizarResultados([]);
                });
                listaResultados.appendChild(li);
            });
        }

        async function buscar(term) {
            try {
                const response = await fetch('api_cnaes.php?q=' + encodeURIComponent(term));
                if (!response.ok) {
                    renderizarResultados([]);
                    return;
                }
                const dados = await response.json();
                renderizarResultados(Array.isArray(dados) ? dados : []);
            } catch (error) {
                console.error('Erro ao consultar CNAE', error);
                renderizarResultados([]);
            }
        }

        inputBusca.addEventListener('input', function () {
            const valor = inputBusca.value.trim();
            inputSelecionado.value = '';

            if (valor.length < 3) {
                renderizarResultados([]);
                ultimoTermo = '';
                return;
            }

            if (valor === ultimoTermo) {
                return;
            }
            ultimoTermo = valor;
            buscar(valor);
        });
    </script>
</body>
</html>
