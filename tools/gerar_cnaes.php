<?php
/**
 * Ferramenta CLI para converter o CSV oficial de CNAE em arquivos otimizados.
 *
 * Uso:
 *   php tools/gerar_cnaes.php
 *
 * O script procura por um arquivo chamado "cnae.csv" na mesma pasta.
 * Ele gera dois arquivos na pasta ../storage:
 *   - cnaes.json  -> usado pelo endpoint AJAX
 *   - cnaes.php   -> array PHP pronto para require
 */

$csvPath = __DIR__ . '/cnae.csv';
$storageDir = dirname(__DIR__) . '/storage';
$jsonPath = $storageDir . '/cnaes.json';
$phpPath = $storageDir . '/cnaes.php';

if (!file_exists($csvPath)) {
    fwrite(STDERR, 'Arquivo cnae.csv nao encontrado em ' . __DIR__ . PHP_EOL);
    exit(1);
}

// Garante que a pasta storage exista.
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        fwrite(STDERR, 'Nao foi possivel criar a pasta storage em ' . $storageDir . PHP_EOL);
        exit(1);
    }
}

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    fwrite(STDERR, 'Nao foi possivel abrir o arquivo CSV.' . PHP_EOL);
    exit(1);
}

$cnaes = [];
$lineNumber = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $lineNumber++;

    if (empty($row)) {
        continue;
    }

    // Ignora o cabecalho caso detectado pelo texto "codigo".
    if ($lineNumber === 1 && isset($row[0]) && stripos($row[0], 'codigo') !== false) {
        continue;
    }

    $codigoRaw = $row[0] ?? '';
    $descricaoRaw = $row[1] ?? '';

    $codigo = preg_replace('/[^0-9]/', '', $codigoRaw);
    $descricao = trim($descricaoRaw);

    if ($codigo === '' || $descricao === '') {
        // Pula linhas sem codigo ou descricao.
        continue;
    }

    $cnaes[$codigo] = $descricao;
}
fclose($handle);

ksort($cnaes, SORT_STRING);

// Salva JSON.
$jsonData = json_encode($cnaes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonData === false || file_put_contents($jsonPath, $jsonData) === false) {
    fwrite(STDERR, 'Falha ao gravar ' . $jsonPath . PHP_EOL);
    exit(1);
}

// Salva PHP.
$phpArrayExport = var_export($cnaes, true);
$phpContent = "<?php\n\$segmentosDisponiveis = {$phpArrayExport};\n";
if (file_put_contents($phpPath, $phpContent) === false) {
    fwrite(STDERR, 'Falha ao gravar ' . $phpPath . PHP_EOL);
    exit(1);
}

echo 'CNAEs processados: ' . count($cnaes) . PHP_EOL;
