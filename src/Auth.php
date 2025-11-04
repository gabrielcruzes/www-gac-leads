<?php
/**
 * src/Auth.php
 *
 * Authentication helpers: registration, login and session control.
 */

namespace App;

use PDOException;

require_once __DIR__ . '/Database.php';

class Auth
{
    /**
     * Register a new user and grant initial credits.
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
                ':credits' => 1, // initial credit
                ':role' => $role,
            ]);
        } catch (PDOException $e) {
            // TODO: log the error instead of swallowing it silently.
            return false;
        }
    }

    /**
     * Perform login and store the user id in session.
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
     * Destroy the current session.
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
     * Check if a user is logged in.
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check whether the authenticated user has admin privileges.
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
     * Return the authenticated user.
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
     * Enforce authentication in routes.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Enforce admin access.
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
