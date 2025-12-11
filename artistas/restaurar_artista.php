<?php
// Arquivo: restaurar_artista.php
// Responsável por restaurar um artista da Lixeira (ativo = 0 -> ativo = 1).

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
    // Redireciona para a Lixeira, já que a ação é de restauração
    header("Location: artistas.php?view_status=0&status=erro&msg=" . urlencode($msg));
    exit;
}

// 2. EXECUÇÃO DA RESTAURAÇÃO (UPDATE ativo = 1)
try {
    $sql = "UPDATE artistas SET ativo = 1 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id_artista, PDO::PARAM_INT);
    $stmt->execute();
    
    // Verifica se alguma linha foi afetada
    if ($stmt->rowCount() > 0) {
        $msg = "Artista restaurado com sucesso! Agora ele está visível na lista de Artistas Ativos.";
        $tipo = 'sucesso';
    } else {
        $msg = "Erro: Artista não encontrado ou já estava Ativo.";
        $tipo = 'alerta';
    }

    // 3. REDIRECIONAMENTO DE VOLTA PARA A LIXEIRA (para o usuário ver a mudança)
    header("Location: artistas.php?view_status=0&status={$tipo}&msg=" . urlencode($msg));
    exit;

} catch (\PDOException $e) {
    // Em caso de erro do banco de dados
    // Para debug: $erro_msg = "Erro ao restaurar artista: " . $e->getMessage();
    $erro_msg = "Erro interno ao tentar restaurar o artista.";
    header("Location: artistas.php?view_status=0&status=erro&msg=" . urlencode($erro_msg));
    exit;
}
?>