<?php
/**
 * src/Auth.php
 *
 * Regras de autenticação simples: registro, login e controle de sessão.
 */

namespace App;

use PDOException;

require_once __DIR__ . '/Database.php';

class Auth
{
    /**
     * Registra um novo usuário e concede créditos iniciais.
     */
    public static function register(string $name, string $email, string $password): bool
    {
        $pdo = Database::getConnection();

        try {
            $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $hasAdmin = (int) ($adminCountStmt->fetchColumn() ?: 0) > 0;
            $role = $hasAdmin ? 'user' : 'admin';

            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, credits, role) VALUES (:name, :email, :password_hash, :credits, :role)');
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            return $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':password_hash' => $passwordHash,
                ':credits' => 1, // credito inicial padrao
                ':role' => $role,
            ]);
        } catch (PDOException $e) {
            // TODO registrar o erro em um log centralizado
            return false;
        }
    }

    /**
     * Realiza login e persiste o ID do usuário em sessão.
     */
    public static function login(string $email, string $password): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);

        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        return true;
    }

    /**
     * Remove os dados da sessão e efetua logout.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Verifica se há usuário autenticado.
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Verifica se o usuário autenticado possui perfil admin.
     */
    public static function isAdmin(): bool
    {
        if (!self::check()) {
            return false;
        }

        if (isset($_SESSION['user_role'])) {
            return $_SESSION['user_role'] === 'admin';
        }

        $user = self::user();

        return $user !== null && ($user['role'] ?? 'user') === 'admin';
    }

    /**
     * Retorna o usuário autenticado.
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Garante acesso autenticado, redirecionando para login quando necessário.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Garante acesso exclusivo a admins.
     */
    public static function requireAdmin(): void
    {
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }

        if (!self::isAdmin()) {
            $_SESSION['flash_error'] = 'Voce nao tem permissao para acessar esta area.';
            header('Location: /index.php');
            exit;
        }
    }
}




