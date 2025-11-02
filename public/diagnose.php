<?php
/**
 * public/diagnose.php
 *
 * Script temporario para diagnosticar conexao e configuracao do banco.
 * REMOVER apos resolver os problemas de deploy.
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/plain');

echo "Diagnostico da aplicacao\n";
echo "-------------------------\n";
echo "APP_ENV: " . env('APP_ENV', 'undefined') . "\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'not defined') . "\n";
echo "DB_PORT: " . (defined('DB_PORT') ? DB_PORT : 'not defined') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'not defined') . "\n";
echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'not defined') . "\n";
echo "DB_CHARSET: " . (defined('DB_CHARSET') ? DB_CHARSET : 'not defined') . "\n";
echo "\n";

try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            defined('DB_PORT') ? (int) DB_PORT : 3306,
            DB_NAME,
            defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
        ),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $stmt = $pdo->query('SELECT 1');
    echo "Conexao com MySQL: OK\n";
    echo "SELECT 1 retornou: " . $stmt->fetchColumn() . "\n";
} catch (Throwable $exception) {
    echo "Conexao com MySQL: FALHOU\n";
    echo "Mensagem: " . $exception->getMessage() . "\n";
    echo "Trace: " . $exception->getTraceAsString() . "\n";
    exit;
}

echo "\n";
echo "Tabelas disponiveis em " . DB_NAME . ":\n";

try {
    $tablesStmt = $pdo->query('SHOW TABLES');
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "- Nenhuma tabela encontrada.\n";
    } else {
        foreach ($tables as $table) {
            echo "- {$table}\n";
        }
    }
} catch (Throwable $tableException) {
    echo "Nao foi possivel listar as tabelas:\n";
    echo $tableException->getMessage() . "\n";
    exit;
}

echo "\n";
echo "Este arquivo deve ser removido apos o diagnostico.\n";
