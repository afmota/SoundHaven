<?php
// Arquivo: colecao.php (Controlador)

session_start();

// 1. Incluir Dependências e Conexão (CORRIGIDO: O config.php estabelece $pdo)
require_once '../src/config/config.php'; // Caminho corrigido
require_once '../src/Model/AlbumModel.php';
// require_once 'funcoes.php'; // Se 'funcoes.php' não for mais usado, pode ser removido.

// Verifica autenticação e obtém o ID do usuário (Assumindo que o user_id está na sessão)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
// A variável $pdo JÁ ESTÁ DISPONÍVEL após o require_once 'src/config/config.php'
// Removida a chamada $pdo = getPDOConnection();

$albumModel = new AlbumModel($pdo);


// 2. Coletar e Sanitizar Inputs
// Lógica de Paginação
$limite_por_pagina = 25; 
$pagina_atual = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT) ?? 1;
$pagina_atual = max(1, $pagina_atual); // Garante que a página é >= 1
$offset = ($pagina_atual - 1) * $limite_por_pagina;

// view_status: 1 (Ativos - padrão), 0 (Lixeira/Excluídos)
$view_status = filter_input(INPUT_GET, 'view_status', FILTER_VALIDATE_INT);
$view_status = ($view_status === 0) ? 0 : 1; // Garante 0 ou 1

// Filtro de Formato
$filtro_formato = filter_input(INPUT_GET, 'formato', FILTER_SANITIZE_STRING);
$filtro_formato = empty($filtro_formato) ? null : $filtro_formato;

// Variáveis para a barra de título
$filtro_titulo_extra = "";
if ($filtro_formato) {
    $filtro_titulo_extra = " (Formato: " . htmlspecialchars($filtro_formato) . ")";
}
$page_title = ($view_status == 0) ? 'Lixeira da Coleção' : 'Sua Coleção Pessoal';
$page_title .= $filtro_titulo_extra; 


// 3. Processar Lógica (Chamar o Model)
$colecao = [];
$total_itens = 0;
$total_paginas = 0;
$erro_db = null;

try {
    // Busca o total de itens (para a paginação)
    $total_itens = $albumModel->countTotalAlbumsFiltered($userId, $filtro_formato, $view_status);
    $total_paginas = ceil($total_itens / $limite_por_pagina);
    
    // Evita busca desnecessária se o offset for maior que o total ou se a página for inválida
    if ($offset < $total_itens) {
        // Busca a lista de álbuns
        $colecao = $albumModel->getAlbumsListFiltered($userId, $limite_por_pagina, $offset, $filtro_formato, $view_status);
    }

} catch (Exception $e) {
    $erro_db = "Erro fatal ao carregar a coleção: " . $e->getMessage();
    error_log($erro_db);
    // Em caso de erro, a coleção permanece vazia.
}

/**
 * Função auxiliar para gerar o link da paginação, mantendo o view_status E o formato
 * @param int $pagina
 * @param int $view_status
 * @param string|null $formato
 * @return string
 */
function getPaginationLink($pagina, $view_status, $formato = null) {
    $url = "colecao.php?p=$pagina";
    if ($view_status !== 1) { 
        $url .= "&view_status=$view_status";
    }
    if ($formato) { // Adiciona o formato ao link de paginação
        $url .= "&formato=" . urlencode($formato);
    }
    return $url;
}


// 4. Inclusão da Visualização (Separando o PHP do HTML)
require_once '../include/header.php';
require_once '../src/View/colecao.view.php';
require_once '../include/footer.php';