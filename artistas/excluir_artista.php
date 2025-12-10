<?php
// Arquivo: excluir_artista.php
// Responsável por executar o Soft Delete (mover para a lixeira) de um artista.

// Redireciona para o arquivo principal se o acesso não for via GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: artistas.php');
    exit;
}

require_once '../db/conexao.php';
// require_once '../seguranca.php'; // Inclua sua checagem de login/admin se aplicável

// 1. OBTENÇÃO E VALIDAÇÃO DO ID
$id_artista = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_artista) {
    $msg = "ID do artista inválido ou ausente.";
    header("Location: artistas.php?status=erro&msg=" . urlencode($msg));
    exit;
}

// 2. EXECUÇÃO DO SOFT DELETE (UPDATE ativo = 0)
try {
    $sql = "UPDATE artistas SET ativo = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id_artista, PDO::PARAM_INT);
    $stmt->execute();
    
    // Verifica se alguma linha foi afetada (o artista realmente existia e estava ativo=1)
    if ($stmt->rowCount() > 0) {
        $msg = "Artista movido para a **Lixeira** com sucesso! Você pode restaurá-lo na seção Lixeira.";
        $tipo = 'sucesso';
    } else {
        $msg = "Erro: Artista não encontrado ou já estava na Lixeira.";
        $tipo = 'alerta';
    }

    // 3. REDIRECIONAMENTO DE VOLTA PARA A LISTA
    header("Location: artistas.php?view_status=1&status={$tipo}&msg=" . urlencode($msg));
    exit;

} catch (\PDOException $e) {
    // Em caso de erro do banco de dados
    // Para debug: $erro_msg = "Erro ao mover para lixeira: " . $e->getMessage();
    $erro_msg = "Erro interno ao tentar mover o artista para a Lixeira.";
    header("Location: artistas.php?view_status=1&status=erro&msg=" . urlencode($erro_msg));
    exit;
}
?>