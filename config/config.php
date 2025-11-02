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

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_NAME', env('DB_NAME', 'leads-gac'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', '123'));

define('CASA_DOS_DADOS_API_KEY', env('CASA_DOS_DADOS_API_KEY', 'changeme'));
define('LEAD_VIEW_COST', (int) env('LEAD_VIEW_COST', 1));

date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
