<?php
// Arquivo: fetch_album_details.php
// Endpoint AJAX para buscar todos os detalhes de um único item da coleção e retornar JSON.

require_once "../db/conexao.php";
require_once "../funcoes.php"; // Para formatar_data

header('Content-Type: application/json');

$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$colecao_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da coleção inválido.']);
    exit;
}

try {
    // 1. Query principal para buscar o álbum com seus dados 1:N
    $sql_colecao = "
        SELECT 
            c.id, 
            c.titulo, 
            c.data_aquisicao, 
            c.data_lancamento,
            c.condicao,
            c.preco,
            c.numero_catalogo,
            c.observacoes,
            c.capa_url, 
            f.descricao AS formato_descricao,
            g.nome AS gravadora_nome
        FROM colecao AS c
        LEFT JOIN formatos AS f ON c.formato_id = f.id
        LEFT JOIN gravadoras AS g ON c.gravadora_id = g.id
        WHERE c.id = :id";

    $stmt_colecao = $pdo->prepare($sql_colecao);
    $stmt_colecao->execute([':id' => $colecao_id]);
    $album = $stmt_colecao->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Álbum não encontrado ou inativo.']);
        exit;
    }

    // 2. Busca dos relacionamentos M:N (Agrupados)
    $relacionamentos = [
        "artistas" => "colecao_artista",
        "produtores" => "colecao_produtor",
        "generos" => "colecao_genero",
        "estilos" => "colecao_estilo",
    ];

    $album["relacionamentos"] = [];

    foreach ($relacionamentos as $nome_relacionamento => $tabela_relacao) {

        // Determina a tabela auxiliar (artistas, produtores, generos, estilos)
        $tabela_aux = $nome_relacionamento;

        // Determina o nome da coluna de ID (singular_nome + _id)
        $coluna_base_name = rtrim($nome_relacionamento, "s");
        if ($nome_relacionamento === "produtores") {
            $coluna_base_name = "produtor";
        }
        $coluna_id = $coluna_base_name . "_id";

        // Define a coluna de exibição e se usaremos 'nome' ou 'descricao'
        $coluna_display = "nome";
        if (
            $nome_relacionamento == "generos" ||
            $nome_relacionamento == "estilos"
        ) {
            $coluna_display = "descricao";
        }

        // Query para buscar as associações
        $sql_mn = "
            SELECT 
                t.{$coluna_display} AS descricao 
            FROM {$tabela_relacao} AS cr
            INNER JOIN {$tabela_aux} AS t ON cr.{$coluna_id} = t.id
            WHERE cr.colecao_id = :id
            ORDER BY t.{$coluna_display} ASC";

        $stmt_mn = $pdo->prepare($sql_mn);
        $stmt_mn->execute([':id' => $colecao_id]);
        // Pega apenas a coluna de descrição em um array simples
        $resultados = $stmt_mn->fetchAll(PDO::FETCH_COLUMN, 0); 

        $album["relacionamentos"][$nome_relacionamento] = $resultados;
    }

    // Formatação adicional para a interface
    $album['data_aquisicao_formatada'] = formatar_data($album['data_aquisicao']);
    $album['data_lancamento_formatada'] = formatar_data($album['data_lancamento']);
    $album['preco_formatado'] = $album['preco'] !== null ? 'R$ ' . number_format($album["preco"], 2, ",", ".") : 'N/A';
    
    // Retorna o álbum formatado
    echo json_encode(['success' => true, 'album' => $album]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
}
?>