<?php
/**
 * public/logout.php
 *
 * Finaliza a sessão atual e redireciona para o login.
 */

use App\Auth;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::logout();
header('Location: login.php');
exit;
