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
    $isAdmin = $user && (($user['role'] ?? '') === 'admin');
    $logoPath = file_exists(__DIR__ . '/assets/images/logo.png') ? 'assets/images/logo.png' : 'assets/images/logo.svg';
    $navLinks = [
        ['href' => 'index.php', 'label' => 'Dashboard', 'key' => 'dashboard'],
        ['href' => 'buscar-leads.php', 'label' => 'Buscar Leads', 'key' => 'buscar'],
        ['href' => 'listas.php', 'label' => 'Listas', 'key' => 'listas'],
        ['href' => 'comprar-creditos.php', 'label' => 'Comprar Créditos', 'key' => 'creditos'],
        ['href' => 'historico-exportacoes.php', 'label' => 'Exportações', 'key' => 'historico'],
    ];
    if ($isAdmin) {
        $navLinks[] = ['href' => 'admin/index.php', 'label' => 'Admin', 'key' => 'admin'];
    }
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
                        <img src="<?php echo $logoPath; ?>" alt="GAC Leads" class="h-8 w-auto">
                        <span class="text-xl font-semibold text-blue-700 hidden sm:inline">GAC Leads</span>
                    </a>
                </div>
                <div class="flex items-center gap-4">
                    <button
                        type="button"
                        class="md:hidden inline-flex items-center justify-center rounded-lg border border-slate-200 p-2 text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        id="mobileMenuButton"
                        aria-label="Abrir menu"
                        aria-expanded="false"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <nav class="hidden md:flex items-center gap-4 text-sm font-medium">
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
                    <div class="hidden md:flex items-center gap-3">
                        <span class="text-sm text-slate-600">Olá, <?php echo htmlspecialchars($userName); ?></span>
                        <a href="logout.php" class="text-sm text-slate-500 hover:text-blue-600">Sair</a>
                    </div>
                </div>
            </div>
            <div id="mobileMenu" class="md:hidden hidden border-t border-slate-200 bg-white">
                <nav class="flex flex-col px-6 py-4 text-sm font-medium">
                    <?php foreach ($navLinks as $link): ?>
                        <?php $isActive = $active === $link['key']; ?>
                        <a
                            href="<?php echo $link['href']; ?>"
                            class="rounded-lg px-3 py-2 <?php echo $isActive ? 'bg-blue-600 text-white' : 'text-blue-700 hover:bg-blue-50'; ?>"
                        >
                            <?php echo $link['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="border-t border-slate-200 px-6 py-4 flex items-center justify-between text-sm">
                    <span class="text-slate-600">Olá, <?php echo htmlspecialchars($userName); ?></span>
                    <a href="logout.php" class="text-slate-500 hover:text-blue-600">Sair</a>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var menuButton = document.getElementById('mobileMenuButton');
                    var mobileMenu = document.getElementById('mobileMenu');
                    if (!menuButton || !mobileMenu) {
                        return;
                    }
                    menuButton.addEventListener('click', function () {
                        var isHidden = mobileMenu.classList.toggle('hidden');
                        menuButton.setAttribute('aria-expanded', (!isHidden).toString());
                    });
                    mobileMenu.querySelectorAll('a').forEach(function (link) {
                        link.addEventListener('click', function () {
                            mobileMenu.classList.add('hidden');
                            menuButton.setAttribute('aria-expanded', 'false');
                        });
                    });
                });
            </script>
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
