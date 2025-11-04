<?php
/**
 * public/components.php
 *
 * Funções auxiliares para renderização de layout com Tailwind.
 */

use App\Auth;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

/**
 * Renderiza o início da página com cabeçalho e navegação.
 */
function renderPageStart(string $title, string $active = ''): void
{
    $user = Auth::user();
    $userName = $user ? $user['name'] : 'Usuário';
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | GAC Leads</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
        <link rel="shortcut icon" href="assets/images/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; }
        </style>
    </head>
    <body class="bg-slate-100 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <header class="bg-white shadow">
            <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2">
                        <img src="assets/images/logo.svg" alt="GAC Leads" class="h-8 w-auto">
                        <span class="text-xl font-semibold text-blue-700 hidden sm:inline">GAC Leads</span>
                    </a>
                </div>
                <nav class="flex items-center gap-4 text-sm font-medium">
                    <a href="index.php" class="px-3 py-2 rounded-lg <?php echo $active === 'dashboard' ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>">Dashboard</a>
                    <a href="buscar-leads.php" class="px-3 py-2 rounded-lg <?php echo $active === 'buscar' ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>">Buscar Leads</a>
                    <a href="listas.php" class="px-3 py-2 rounded-lg <?php echo $active === 'listas' ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>">Listas</a>
                    <a href="comprar-creditos.php" class="px-3 py-2 rounded-lg <?php echo $active === 'creditos' ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>">Comprar Créditos</a>
                    <a href="historico-exportacoes.php" class="px-3 py-2 rounded-lg <?php echo $active === 'historico' ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>">Exportações</a>
                </nav>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-600">Olá, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="logout.php" class="text-sm text-slate-500 hover:text-blue-600">Sair</a>
                </div>
            </div>
        </header>
        <main class="flex-1">
            <div class="max-w-6xl mx-auto px-6 py-10">
    <?php
}

/**
 * Renderiza o fim da página.
 */
function renderPageEnd(): void
{
    ?>
            </div>
        </main>
        <footer class="bg-white border-t">
            <div class="max-w-6xl mx-auto px-6 py-4 text-sm text-slate-500">
                &copy; <?php echo date('Y'); ?> GAC Leads. Todos os direitos reservados.
            </div>
        </footer>
    </div>
    </body>
    </html>
    <?php
}
