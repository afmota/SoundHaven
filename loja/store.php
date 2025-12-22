<?php
// Arquivo: store.php (Raiz)
require_once '../src/Controller/StoreController.php';
require_once '../src/config/config.php'; // Sua conexão original
/** @var PDO $pdo */
require_once '../src/functions/funcoes.php';    // Suas funções de ajuda

// 1. Instancia o Controller
$controller = new StoreController();

// 2. Chama a lógica e recebe os dados mastigados
$dados = $controller->index();

// 3. "Extrai" os dados para variáveis simples (ex: $albuns, $total_paginas)
extract($dados);

// 4. Carrega os dropdowns (ainda necessários para a sidebar)
// Podemos depois mover isso para o Controller também
$artistas = $pdo->query("SELECT id, nome FROM artistas ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$tipos = $pdo->query("SELECT id, descricao FROM tipo_album ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$situacoes = $pdo->query("SELECT id, descricao FROM situacao ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);
$formatos = $pdo->query("SELECT id, descricao FROM formatos ORDER BY descricao ASC")->fetchAll(PDO::FETCH_ASSOC);

// 5. Inclui a parte visual (A View)
require_once '../views/store/index.php';