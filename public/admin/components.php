<?php
/**
 * public/admin/components.php
 *
 * Componentes reutilizaveis para o painel administrativo.
 */

use App\Auth;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Auth.php';

/**
 * Renderiza o layout base do painel administrativo.
 */
function renderAdminPageStart(string $title, string $active = ''): void
{
    $user = Auth::user();
    $userName = $user ? ($user['name'] ?? 'Administrador') : 'Administrador';
    $navLinks = [
        ['href' => 'index.php', 'label' => 'Visao geral', 'key' => 'dashboard'],
        ['href' => 'users.php', 'label' => 'Usuarios', 'key' => 'users'],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | Painel Admin</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg">
        <link rel="shortcut icon" href="../assets/images/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Inter', sans-serif; }
        </style>
    </head>
    <body class="bg-slate-100 min-h-screen">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white shadow-sm">
                <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <a href="../index.php" class="flex items-center gap-2">
                            <img src="../assets/images/logo.svg" alt="GAC Leads" class="h-8 w-auto">
                            <span class="text-xl font-semibold text-blue-700 hidden sm:inline">GAC Leads • Admin</span>
                        </a>
                    </div>
                    <nav class="hidden md:flex items-center gap-3 text-sm font-medium">
                        <?php foreach ($navLinks as $link): ?>
                            <?php $isActive = $active === $link['key']; ?>
                            <a
                                href="<?php echo $link['href']; ?>"
                                class="px-3 py-2 rounded-lg <?php echo $isActive ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>"
                            >
                                <?php echo $link['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-slate-600 hidden sm:inline">Ola, <?php echo htmlspecialchars($userName); ?></span>
                        <a href="../index.php" class="text-slate-500 hover:text-blue-600">Area do cliente</a>
                        <a href="../logout.php" class="text-slate-500 hover:text-blue-600">Sair</a>
                    </div>
                </div>
                <div class="md:hidden border-t border-slate-200 bg-white">
                    <nav class="flex gap-1 px-4 py-2 text-sm font-medium overflow-x-auto">
                        <?php foreach ($navLinks as $link): ?>
                            <?php $isActive = $active === $link['key']; ?>
                            <a
                                href="<?php echo $link['href']; ?>"
                                class="whitespace-nowrap rounded-lg px-3 py-2 <?php echo $isActive ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>"
                            >
                                <?php echo $link['label']; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </header>
            <main class="flex-1">
                <div class="max-w-6xl mx-auto px-6 py-10">
    <?php
}

/**
 * Renderiza o encerramento da pagina.
 */
function renderAdminPageEnd(): void
{
    ?>
                </div>
            </main>
            <footer class="bg-white border-t">
                <div class="max-w-6xl mx-auto px-6 py-4 text-sm text-slate-500">
                    Painel administrativo • <?php echo date('Y'); ?>
                </div>
            </footer>
        </div>
    </body>
    </html>
    <?php
}

