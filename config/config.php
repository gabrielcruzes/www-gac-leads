<?php
/**
 * config/config.php
 *
 * Carrega configuracoes a partir de variaveis de ambiente definidas no .env ou no container.
 * Nunca armazene credenciais sensiveis diretamente no controle de versao.
 */

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';

if (!function_exists('loadEnvFile')) {
    /**
     * Carrega variaveis definidas em um arquivo .env simples.
     *
     * Suporta comentarios (# ou ;) e valores com aspas simples/duplas.
     * Mantem intactas variaveis ja definidas no ambiente.
     */
    function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = trim(substr($line, 7));
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $line, 2);

            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }

            $value = trim($rawValue);

            // Remove comentarios inline quando o valor nao esta delimitado por aspas.
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $commentPos = strpos($value, ' #');
                if ($commentPos !== false) {
                    $value = substr($value, 0, $commentPos);
                }

                $commentPos = strpos($value, ' ;');
                if ($commentPos !== false) {
                    $value = substr($value, 0, $commentPos);
                }

                $value = trim($value);
            }

            // Remove aspas externas, se existirem.
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Converte escapes simples de nova linha e retorno de carro.
            $value = str_replace(['\n', '\r'], ["\n", "\r"], $value);

            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            putenv(sprintf('%s=%s', $key, $value));
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Carrega valores do .env local, se existir.
if (file_exists($envFile)) {
    loadEnvFile($envFile);
}

// Ajusta informacoes de proxy reverso (Traefik / Dokploy) para HTTPS.
$forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? null;
if (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

if (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && ctype_digit((string) $_SERVER['HTTP_X_FORWARDED_PORT'])) {
    $_SERVER['SERVER_PORT'] = (int) $_SERVER['HTTP_X_FORWARDED_PORT'];
}

if (!function_exists('env')) {
    /**
     * Recupera uma variavel de ambiente com valor padrao opcional.
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('parseDatabaseUrl')) {
    /**
     * Interpreta uma URL de conexao MySQL (ex.: mysql://user:pass@host:3306/db?charset=utf8mb4).
     *
     * @return array{host?:string,port?:int,user?:string,pass?:string,name?:string,charset?:string}|array{}
     */
    function parseDatabaseUrl(string $url): array
    {
        if ($url === '') {
            return [];
        }

        // Permite strings do tipo mysql:// sem obrigatoriamente ter esquema http.
        if (strpos($url, '://') === false) {
            return [];
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return [];
        }

        $database = isset($parts['path']) ? ltrim($parts['path'], '/') : '';

        $config = [
            'host' => $parts['host'],
        ];

        if (isset($parts['port'])) {
            $config['port'] = (int) $parts['port'];
        }

        if (isset($parts['user'])) {
            $config['user'] = $parts['user'];
        }

        if (isset($parts['pass'])) {
            $config['pass'] = $parts['pass'];
        }

        if ($database !== '') {
            $config['name'] = $database;
        }

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            if (isset($queryParams['charset'])) {
                $config['charset'] = $queryParams['charset'];
            }
        }

        return $config;
    }
}

// Inicializa configuracao padrao
$databaseConfig = [
    'host' => env('DB_HOST'),
    'name' => env('DB_NAME'),
    'user' => env('DB_USER'),
    'pass' => env('DB_PASS'),
    'port' => env('DB_PORT'),
    'charset' => env('DB_CHARSET'),
];

// Verifica variacoes de URL completas
$databaseUrlKeys = [
    'DATABASE_URL',
    'DB_URL',
    'JAWSDB_URL',
    'CLEARDB_DATABASE_URL',
];

$databaseUrl = null;
foreach ($databaseUrlKeys as $urlKey) {
    $candidate = env($urlKey);
    if ($candidate) {
        $databaseUrl = $candidate;
        break;
    }
}

if ($databaseUrl) {
    $parsed = parseDatabaseUrl($databaseUrl);
    foreach ($parsed as $key => $value) {
        if ($value !== null && $value !== '') {
            $databaseConfig[$key] = $value;
        }
    }
}

// Aceita DB_HOST no formato mysql://user:pass@host:port/db
$hostValue = $databaseConfig['host'] ?? null;
if (is_string($hostValue) && strpos($hostValue, '://') !== false) {
    $parsed = parseDatabaseUrl($hostValue);
    foreach ($parsed as $key => $value) {
        if ($key === 'host' || !isset($databaseConfig[$key]) || $databaseConfig[$key] === null || $databaseConfig[$key] === '') {
            $databaseConfig[$key] = $value;
        }
    }
}

// Suporta formato host:porta sem protocolo
if (is_string($databaseConfig['host']) && strpos($databaseConfig['host'], '://') === false) {
    $hostPieces = explode(':', $databaseConfig['host'], 2);
    if (count($hostPieces) === 2 && $hostPieces[0] !== '' && $hostPieces[1] !== '') {
        $databaseConfig['host'] = $hostPieces[0];
        if (empty($databaseConfig['port'])) {
            $databaseConfig['port'] = (int) $hostPieces[1];
        }
    }
}

// Valores padrao finais
$databaseConfig['host'] = $databaseConfig['host'] ?: '127.0.0.1';
$databaseConfig['name'] = $databaseConfig['name'] ?: 'leads-gac';
$databaseConfig['user'] = $databaseConfig['user'] ?: 'root';
$databaseConfig['pass'] = $databaseConfig['pass'] ?? '123';
$databaseConfig['port'] = $databaseConfig['port'] ?: 3306;
$databaseConfig['charset'] = $databaseConfig['charset'] ?: 'utf8mb4';

define('DB_HOST', $databaseConfig['host']);
define('DB_NAME', $databaseConfig['name']);
define('DB_USER', $databaseConfig['user']);
define('DB_PASS', $databaseConfig['pass']);
define('DB_PORT', (int) $databaseConfig['port']);
define('DB_CHARSET', $databaseConfig['charset']);

define('CASA_DOS_DADOS_API_KEY', env('CASA_DOS_DADOS_API_KEY', 'changeme'));
define('LEAD_VIEW_COST', (int) env('LEAD_VIEW_COST', 1));

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
