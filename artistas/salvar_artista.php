<?php
// Arquivo: salvar_artista.php
// Processa a submissão dos formulários de adição e edição de artistas.

// Redireciona para o arquivo principal se o acesso não for via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: artistas.php');
    exit;
}

require_once '../db/conexao.php';
require_once '../funcoes.php'; 
// require_once '../seguranca.php'; // Inclua sua checagem de login/admin se aplicável

// =========================================================================
// 1. OBTENÇÃO E VALIDAÇÃO DOS DADOS
// =========================================================================

// O ID do artista é o fator decisivo: NULL = INSERT; INT = UPDATE
$id_artista = filter_input(INPUT_POST, 'id_artista', FILTER_VALIDATE_INT);
$is_update = !empty($id_artista);

// ------------------------------------
// a) Dados Obrigatórios
// ------------------------------------
$nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);

if (empty($nome)) {
    $msg = "O campo 'Nome/Apelido do Artista' é obrigatório.";
    $redirect_to = $is_update ? "editar_artista.php?id={$id_artista}" : "adicionar_artista.php";
    header("Location: {$redirect_to}&status=erro&msg=" . urlencode($msg));
    exit;
}

// ------------------------------------
// b) Dados Opcionais e Sanitização
// ------------------------------------
$pais_origem = filter_input(INPUT_POST, 'pais_origem', FILTER_VALIDATE_INT) ?: null;
$genero_principal = filter_input(INPUT_POST, 'genero_principal', FILTER_VALIDATE_INT) ?: null; // ID da tabela 'generos'

// Datas devem ser no formato YYYY-MM-DD. Se inválidas/vazias, usamos NULL no banco.
$data_inicio = filter_input(INPUT_POST, 'data_inicio', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$data_inicio = !empty($data_inicio) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $data_inicio) ? $data_inicio : null;

$data_fim = filter_input(INPUT_POST, 'data_fim', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$data_fim = !empty($data_fim) && preg_match("/^\d{4}-\d{2}-\d{2}$/", $data_fim) ? $data_fim : null;

// URLs e Biografia
$biografia = filter_input(INPUT_POST, 'biografia', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$site_oficial = filter_input(INPUT_POST, 'site_oficial', FILTER_VALIDATE_URL);
$imagem_url = filter_input(INPUT_POST, 'imagem_url', FILTER_VALIDATE_URL);

// O campo 'ativo' é padrão 1 (ativo) para INSERT e deve ser mantido o valor do DB para UPDATE, 
// mas não foi enviado pelo formulário, então não precisamos dele aqui no POST.

// =========================================================================
// 2. MONTAGEM DA QUERY E EXECUÇÃO (INSERT ou UPDATE)
// =========================================================================

try {
    if ($is_update) {
        // --- OPERAÇÃO UPDATE ---
        $sql = "UPDATE artistas SET 
                    nome = :nome,
                    pais_origem = :pais_origem,
                    genero_principal = :genero_principal,
                    data_inicio = :data_inicio,
                    data_fim = :data_fim,
                    biografia = :biografia,
                    site_oficial = :site_oficial,
                    imagem_url = :imagem_url
                WHERE id = :id_artista";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_artista', $id_artista, PDO::PARAM_INT);
        $mensagem_sucesso = "Artista **{$nome}** atualizado com sucesso!";
        $redirect_id = $id_artista;

    } else {
        // --- OPERAÇÃO INSERT ---
        // 'ativo' é definido como 1 por padrão para novos registros (soft delete).
        $sql = "INSERT INTO artistas 
                (nome, pais_origem, genero_principal, data_inicio, data_fim, biografia, site_oficial, imagem_url, ativo)
                VALUES 
                (:nome, :pais_origem, :genero_principal, :data_inicio, :data_fim, :biografia, :site_oficial, :imagem_url, 1)";
        
        $stmt = $pdo->prepare($sql);
        $mensagem_sucesso = "Novo artista **{$nome}** adicionado com sucesso!";
    }

    // ------------------------------------
    // 3. BIND DOS PARÂMETROS (Comuns a INSERT e UPDATE)
    // ------------------------------------
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':pais_origem', $pais_origem, PDO::PARAM_INT);
    $stmt->bindParam(':genero_principal', $genero_principal, PDO::PARAM_INT);
    
    // As datas e URLs podem ser NULL no banco, usamos PARAM_NULL para PDO::PARAM_STR
    $stmt->bindParam(':data_inicio', $data_inicio, $data_inicio === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':data_fim', $data_fim, $data_fim === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':biografia', $biografia);
    $stmt->bindParam(':site_oficial', $site_oficial, $site_oficial === false ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':imagem_url', $imagem_url, $imagem_url === false ? PDO::PARAM_NULL : PDO::PARAM_STR);

    // Executa a Query
    $stmt->execute();

    // Se for um INSERT, obtemos o ID para redirecionar para a edição
    if (!$is_update) {
        $redirect_id = $pdo->lastInsertId();
    }
    
    // =========================================================================
    // 4. SUCESSO E REDIRECIONAMENTO
    // =========================================================================
    
    // Redireciona para a página de edição do artista recém-salvo/atualizado
    header("Location: editar_artista.php?id={$redirect_id}&status=sucesso&msg=" . urlencode($mensagem_sucesso));
    exit;

} catch (\PDOException $e) {
    // =========================================================================
    // 5. ERRO E REDIRECIONAMENTO
    // =========================================================================
    
    // Mensagem de erro que pode ser específica (Ex: Violação de UNIQUE KEY)
    if ($e->getCode() == 23000) { // Código de erro SQL para Integrity constraint violation (pode ser UNIQUE key)
        $erro_msg = "Erro de integridade de dados. O nome '{$nome}' pode já existir no sistema.";
    } else {
        // Para debug:
        // $erro_msg = "Erro ao salvar artista: " . $e->getMessage();
        // Para produção (melhor):
        $erro_msg = "Erro interno ao tentar salvar o artista. Por favor, tente novamente.";
    }
    
    // Redireciona de volta para o formulário correto (Adição ou Edição)
    $redirect_to = $is_update ? "editar_artista.php?id={$id_artista}" : "adicionar_artista.php";
    header("Location: {$redirect_to}&status=erro&msg=" . urlencode($erro_msg));
    exit;
}

?>