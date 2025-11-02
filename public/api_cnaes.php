<?php
/**
 * Endpoint leve para busca de CNAE.
 *
 * Uso:
 *   GET /public/api_cnaes.php?q=6201
 * Retorno:
 *   [
 *     {"codigo": "6201501", "descricao": "Desenvolvimento de software sob encomenda"},
 *     ...
 *   ]
 */

header('Content-Type: application/json; charset=utf-8');

$storageFile = __DIR__ . '/../storage/cnaes.json';

if (!file_exists($storageFile)) {
    echo '[]';
    exit;
}

$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($query === '') {
    echo '[]';
    exit;
}

$data = json_decode(file_get_contents($storageFile), true);
if (!is_array($data)) {
    echo '[]';
    exit;
}

$queryLower = mb_strtolower($query, 'UTF-8');
$results = [];

foreach ($data as $codigo => $descricao) {
    if (count($results) >= 15) {
        break;
    }

    $codigoStr = (string) $codigo;
    $descricaoStr = (string) $descricao;

    if (
        strpos($codigoStr, $queryLower) !== false ||
        mb_stripos($descricaoStr, $query, 0, 'UTF-8') !== false
    ) {
        $results[] = [
            'codigo' => $codigoStr,
            'descricao' => $descricaoStr,
        ];
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
