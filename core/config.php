<?php
// Arquivo: /core/config.php

// =============================================
// CONFIGURAÇÕES GERAIS DO SISTEMA
// =============================================

// Ambiente: development | production
define('APP_ENV', 'development');

// Ativar exibição de erros apenas no ambiente de desenvolvimento
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// =============================================
// CONFIGURAÇÕES DE BANCO DE DADOS
// =============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'SoundHaven');
define('DB_USER', 'sh_user');
define('DB_PASS', 'W3azxc*9');
define('DB_CHARSET', 'utf8mb4');

// =============================================
// CONFIGURAÇÕES DE SEGURANÇA
// =============================================

// Tempo máximo de sessão (em segundos)
define('SESSION_TIMEOUT', 60 * 60); // 1 hora

// Chave aleatória para tokens CSRF, cookies etc.
// Idealmente gere uma nova chave real mais tarde.
define('APP_SECRET_KEY', 'CHANGE_THIS_SECRET');

// Ativar proteção CSRF global?
define('ENABLE_CSRF', true);
