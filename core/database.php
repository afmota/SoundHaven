<?php
// Arquivo: /core/database.php

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;

    public static function connect() {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                if (APP_ENV === 'development') {
                    die("Erro ao conectar ao banco: " . $e->getMessage());
                } else {
                    error_log("Erro de conexão ao banco.");
                    die("Erro interno. Tente novamente mais tarde.");
                }
            }
        }

        return self::$instance;
    }
}
