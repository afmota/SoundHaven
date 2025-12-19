<?php
// Arquivo: restaurar_colecao.php
// Lógica para RESTAURAR um item da coleção (reverter o soft delete: ativo = 1).

// --- CORREÇÃO CRÍTICA APLICADA: Inicia Output Buffering (OB) ---
// O OB previne erros de 'Headers already sent' que bloqueiam o Location:
ob_start();

// --- CONFIGURAÇÃO DE CAMINHO ---
// Se colecao.php está em http://localhost/colecao/colecao.php, use:
$caminho_base = 'colecao.php'; 
// Se não funcionar, tente o caminho absoluto a partir da raiz do servidor:
// $caminho_base = '/SUA_PASTA_RAIZ/colecao/colecao.php'; 


// 1. Iniciar Sessão e Requires
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../db/conexao.php';

// 2. Obter e validar o ID
$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Função de Redirecionamento segura
function redirecionar(string $base_url, int $view_status) {
    global $caminho_base;
    $location = $caminho_base . "?view_status=" . $view_status;
    
    // Anexa as mensagens de sessão como parâmetros GET, se existirem
    if (isset($_SESSION['tipo_mensagem']) && isset($_SESSION['mensagem_status'])) {
        $location .= "&status=" . $_SESSION['tipo_mensagem'] . "&msg=" . urlencode($_SESSION['mensagem_status']);
        // Limpa as sessões após o uso (boa prática)
        unset($_SESSION['tipo_mensagem']);
        unset($_SESSION['mensagem_status']);
    }

    header("Location: " . $location);
    ob_end_flush(); // Envia o buffer de saída
    exit();
}


if (!$colecao_id) {
    $_SESSION['mensagem_status'] = 'ID da coleção inválido para restauração.';
    $_SESSION['tipo_mensagem'] = 'erro';
    redirecionar($caminho_base, 0); // Volta para a lixeira
}

try {
    // 3. Query de Restauração (UPDATE)
    $sql = "UPDATE colecao SET ativo = 1, atualizado_em = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $colecao_id]);

    // 4. Checar se alguma linha foi afetada
    if ($stmt->rowCount() > 0) {
        // Busca o título para uma mensagem mais informativa
        $sql_titulo = "SELECT titulo FROM colecao WHERE id = :id";
        $stmt_titulo = $pdo->prepare($sql_titulo);
        $stmt_titulo->execute([':id' => $colecao_id]);
        $album = $stmt_titulo->fetch(PDO::FETCH_ASSOC);
        $titulo = $album['titulo'] ?? 'O item';

        $_SESSION['mensagem_status'] = $titulo . ' foi restaurado com sucesso para a sua coleção ativa.';
        $_SESSION['tipo_mensagem'] = 'sucesso';
    } else {
        $_SESSION['mensagem_status'] = 'Falha na restauração: item não encontrado ou já está ativo.';
        $_SESSION['tipo_mensagem'] = 'alerta';
    }

    // 5. Redirecionar para a lista de itens ATIVOS (view_status=1)
    redirecionar($caminho_base, 1);

} catch (\PDOException $e) {
    // Tratamento de erro de banco de dados
    $_SESSION['mensagem_status'] = 'Erro ao tentar restaurar o item: ' . $e->getMessage();
    $_SESSION['tipo_mensagem'] = 'erro';
    redirecionar($caminho_base, 0); // Volta para a lixeira
}
// O ob_end_flush() e o exit() são chamados dentro da função redirecionar.
?>