<?php
/**
 * src/Database.php
 *
 * Gerencia a conexão PDO com o MySQL usando o padrão Singleton.
 */

namespace App;

use PDO;
use PDOException;

require_once __DIR__ . '/../config/config.php';

class Database
{
    /**
     * @var PDO|null
     */
    private static ?PDO $instance = null;

    /**
     * Retorna a instância compartilhada de PDO.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Em produção, logar apropriadamente ao invés de exibir a mensagem.
                die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
