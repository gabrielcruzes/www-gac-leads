<?php
/**
 * config/config.php
 *
 * Carrega configuracoes a partir de variaveis de ambiente definidas no .env ou no container.
 * Nunca armazene credenciais sensiveis diretamente no controle de versao.
 */

$projectRoot = dirname(__DIR__);
$envFile = $projectRoot . DIRECTORY_SEPARATOR . '.env';

// Carrega valores do .env local, se existir.
if (file_exists($envFile)) {
    $envValues = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if (is_array($envValues)) {
        foreach ($envValues as $envKey => $envValue) {
            // Preserva variaveis ja definidas no ambiente do servidor.
            if (getenv($envKey) === false && !array_key_exists($envKey, $_ENV)) {
                putenv(sprintf('%s=%s', $envKey, $envValue));
                $_ENV[$envKey] = $envValue;
            }
        }
    }
}

if (!function_exists('env')) {
    /**
     * Recupera uma variavel de ambiente com valor padrao opcional.
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

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
