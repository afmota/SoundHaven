<?php
// Arquivo: api/fetch_artista_details.php
// Endpoint AJAX para buscar e renderizar a página de detalhes do artista.
// Baseado em fetch_album_details.php (assumimos que o endpoint de álbum carrega o HTML de detalhes_colecao.php).

// 1. Inclusões e Configuração
require_once '../db/conexao.php';
require_once '../funcoes.php';

// O endpoint AJAX geralmente não precisa de sessão para buscar dados públicos,
// mas se for necessário para checagem de permissão, adicione aqui.
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// 2. OBTENÇÃO DO ID
$artista_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// 3. Validação Básica
if (!$artista_id) {
    // Retorna um erro formatado para ser exibido pelo AJAX
    http_response_code(400); // Bad Request
    echo '<div class="alert erro">ID do Artista não fornecido.</div>';
    exit;
}

// 4. Busca dos Dados Principais do Artista
try {
    // SQL para obter os dados principais do artista, incluindo o nome do país
    $sql = "SELECT 
                a.*, 
                p.nome AS nome_pais
            FROM artistas AS a
            LEFT JOIN paises AS p ON a.pais_origem = p.id
            WHERE a.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $artista_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $artista = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$artista) {
        http_response_code(404); // Not Found
        echo '<div class="alert erro">Artista com ID ' . htmlspecialchars($artista_id) . ' não encontrado.</div>';
        exit;
    }

    // 5. Incluir a Página de Detalhes (Renderização do HTML)
    // Em vez de renderizar o HTML aqui, o padrão do seu projeto é incluir o arquivo de detalhes.
    // O arquivo a ser incluído é /artistas/detalhes_artista.php
    
    // Passamos a variável $artista para o escopo do arquivo detalhes_artista.php
    $item = $artista; // Renomeamos para $item para consistência com detalhes_colecao.php
    
    // O status (ativo/lixeira) é importante para definir as ações
    $is_lixeira = ($item['ativo'] == 0); 
    
    // Incluímos o HTML de apresentação
    // Ajuste o caminho de inclusão conforme sua estrutura de pastas
    include '../artistas/detalhes_artista.php';
    
    // NOTA: O arquivo detalhes_artista.php será criado no próximo passo e
    // usará a variável $item (que contém os dados do artista).

} catch (\PDOException $e) {
    http_response_code(500); // Internal Server Error
    // Em ambiente de produção, logar $e->getMessage() e retornar mensagem genérica
    echo '<div class="alert erro">Erro no servidor ao buscar detalhes do artista.</div>';
    // die("Erro: " . $e->getMessage()); // Descomentar para debug
    exit;
}
?>