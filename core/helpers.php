<?php
// Arquivo: /core/helpers.php

require_once __DIR__ . '/config.php';

// ===============================
// SANITIZAÇÃO
// ===============================
function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Sanitiza arrays recursivamente
function sanitize_array($arr) {
    return array_map(function($item) {
        return is_array($item) ? sanitize_array($item) : sanitize($item);
    }, $arr);
}

// ===============================
// REDIRECIONAMENTO
// ===============================
function redirect($url) {
    header("Location: $url");
    exit;
}

// ===============================
// SESSÃO SEGURA
// ===============================
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    } elseif (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        redirect('/login.php');
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}

// ===============================
// CSRF (opcional)
// ===============================
function generate_csrf_token() {
    if (!ENABLE_CSRF) return null;

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (!ENABLE_CSRF) return true;

    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
