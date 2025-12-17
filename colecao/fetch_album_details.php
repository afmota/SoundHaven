<?php
// Arquivo: fetch_album_details.php
// Objetivo: Retornar todos os dados de um álbum via JSON para preencher o Modal.

// 1. Conexões e Dependências
require_once __DIR__ . "/../src/config/config.php"; // O arquivo mestre da conexão
require_once __DIR__ . "/../funcoes.php";

header('Content-Type: application/json');

$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$colecao_id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    // 1. Query Principal (Álbum + Formato + Gravadora)
    $sql = "SELECT c.*, f.descricao AS formato_descricao, g.nome AS gravadora_nome
            FROM colecao c
            LEFT JOIN formatos f ON c.formato_id = f.id
            LEFT JOIN gravadoras g ON c.gravadora_id = g.id
            WHERE c.id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $colecao_id]);
    $album = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$album) {
        echo json_encode(['success' => false, 'message' => 'Álbum não encontrado.']);
        exit;
    }

    // 2. Busca Relacionamentos M:N (Usando sua lógica de loop)
    $relacoes = [
        "artistas"   => ["tab" => "colecao_artista", "col" => "artista_id", "disp" => "nome"],
        "produtores" => ["tab" => "colecao_produtor", "col" => "produtor_id", "disp" => "nome"],
        "generos"    => ["tab" => "colecao_genero",   "col" => "genero_id",  "disp" => "descricao"],
        "estilos"    => ["tab" => "colecao_estilo",   "col" => "estilo_id",  "disp" => "descricao"]
    ];

    $album["relacionamentos"] = [];

    foreach ($relacoes as $key => $conf) {
        $sql_mn = "SELECT t.{$conf['disp']} as valor 
                   FROM {$conf['tab']} AS cr
                   JOIN {$key} AS t ON cr.{$conf['col']} = t.id
                   WHERE cr.colecao_id = :id
                   ORDER BY valor ASC";
        
        $stmt_mn = $pdo->prepare($sql_mn);
        $stmt_mn->execute([':id' => $colecao_id]);
        $album["relacionamentos"][$key] = $stmt_mn->fetchAll(PDO::FETCH_COLUMN);
    }

    // 3. Busca de Faixas
    $sql_faixas = "SELECT numero_faixa, titulo, duracao 
                   FROM colecao_faixas 
                   WHERE colecao_id = :id 
                   ORDER BY numero_faixa ASC";
    $stmt_faixas = $pdo->prepare($sql_faixas);
    $stmt_faixas->execute([':id' => $colecao_id]);
    $album["faixas"] = $stmt_faixas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Formatações para facilitar o trabalho do JavaScript
    $album['data_aquisicao_pt'] = formatar_data($album['data_aquisicao']);
    $album['data_lancamento_pt'] = formatar_data($album['data_lancamento']);
    $album['preco_formatado'] = ($album['preco'] > 0) ? 'R$ ' . number_format($album["preco"], 2, ",", ".") : 'N/D';

    echo json_encode(['success' => true, 'album' => $album]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}