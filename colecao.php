<?php
// Arquivo: colecao.php
// Listagem dos itens da Coleção Pessoal (tabela 'colecao').

require_once 'conexao.php';
require_once 'funcoes.php'; 

// ----------------------------------------------------
// 1. CARREGAR DADOS DA COLEÇÃO COM RELACIONAMENTOS
// ----------------------------------------------------

$colecao = [];

try {
    // 1. Query principal para buscar os álbuns da coleção com seus dados 1:N
    $sql_colecao = "
        SELECT 
            c.id, 
            c.titulo, 
            c.data_aquisicao, 
            c.data_lancamento,
            c.condicao,
            c.preco,
            f.descricao AS formato_descricao,
            g.nome AS gravadora_nome
        FROM colecao AS c
        LEFT JOIN formatos AS f ON c.formato_id = f.id
        LEFT JOIN gravadoras AS g ON c.gravadora_id = g.id
        ORDER BY c.data_aquisicao DESC, c.titulo ASC";
        
    $stmt_colecao = $pdo->query($sql_colecao);
    $colecao = $stmt_colecao->fetchAll(PDO::FETCH_ASSOC);

    // Array para armazenar IDs para a busca M:N
    $colecao_ids = array_column($colecao, 'id');
    
    // 2. Busca dos relacionamentos M:N (Agrupados para facilitar a exibição)
    $relacionamentos = [
        'artistas' => 'colecao_artista',
        'produtores' => 'colecao_produtor',
        'generos' => 'colecao_genero',
        'estilos' => 'colecao_estilo' // NOVO RELACIONAMENTO
    ];
    
    $dados_mn = [];

    foreach ($relacionamentos as $nome_relacionamento => $tabela_relacao) {
        if (empty($colecao_ids)) break;

        // Determina qual tabela auxiliar (artistas, generos, estilos, etc) usar
        $tabela_aux = rtrim($nome_relacionamento, 's'); // Ex: 'artistas' -> 'artista'
        if ($nome_relacionamento == 'generos' || $nome_relacionamento == 'estilos') {
            $tabela_aux = $nome_relacionamento; // Tabela já é 'generos' ou 'estilos'
        }
        if ($nome_relacionamento == 'artistas') {
             $tabela_aux = 'artistas';
        }
        if ($nome_relacionamento == 'produtores') {
             $tabela_aux = 'produtores';
        }
        
        $coluna_id = $tabela_aux . '_id'; // Ex: artista_id, genero_id
        
        // Query para buscar todas as associações de uma vez
        $sql_mn = "
            SELECT 
                cr.colecao_id, 
                t.nome AS descricao -- usando 'nome' para artistas/produtores
            FROM {$tabela_relacao} AS cr
            INNER JOIN {$tabela_aux} AS t ON cr.{$coluna_id} = t.id
            WHERE cr.colecao_id IN (" . implode(',', $colecao_ids) . ")
            ORDER BY cr.colecao_id, t.nome ASC";

        // Ajuste para tabelas que usam 'descricao' em vez de 'nome'
        if ($nome_relacionamento == 'generos' || $nome_relacionamento == 'estilos') {
             $sql_mn = "
                SELECT 
                    cr.colecao_id, 
                    t.descricao
                FROM {$tabela_relacao} AS cr
                INNER JOIN {$tabela_aux} AS t ON cr.{$coluna_id} = t.id
                WHERE cr.colecao_id IN (" . implode(',', $colecao_ids) . ")
                ORDER BY cr.colecao_id, t.descricao ASC";
        }


        $stmt_mn = $pdo->query($sql_mn);
        $resultados = $stmt_mn->fetchAll(PDO::FETCH_ASSOC);

        // Organiza os resultados por colecao_id para injeção
        foreach ($resultados as $row) {
            $colecao_id = $row['colecao_id'];
            $descricao = $row['descricao'] ?? $row['nome']; // Tenta descricao ou nome
            $dados_mn[$colecao_id][$nome_relacionamento][] = $descricao;
        }
    }

    // Mescla os dados M:N na listagem principal
    foreach ($colecao as $key => $album) {
        $id = $album['id'];
        $colecao[$key]['relacionamentos'] = $dados_mn[$id] ?? [];
    }


} catch (\PDOException $e) {
    die("Erro ao carregar coleção: " . $e->getMessage());
}

// ----------------------------------------------------
// 2. HTML DA PÁGINA
// ----------------------------------------------------
require_once 'header.php'; 
?>

<div class="container">
    <h1 style="margin-bottom: 20px;">Sua Coleção Pessoal (Total: <?php echo count($colecao); ?> itens)</h1>

    <div class="colecao-list">
        <?php if (empty($colecao)): ?>
            <p>Sua coleção está vazia. Adicione itens a partir do Catálogo!</p>
        <?php else: ?>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Artistas</th>
                        <th>Gêneros/Estilos</th>
                        <th>Formato/Condição</th>
                        <th>Aquisição</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colecao as $album): 
                        
                        // Formata a lista de artistas
                        $artistas_display = $album['relacionamentos']['artistas'] ?? ['N/A'];
                        $artistas_str = implode(', ', $artistas_display);

                        // Formata a lista de Gêneros e Estilos
                        $generos_arr = $album['relacionamentos']['generos'] ?? [];
                        $estilos_arr = $album['relacionamentos']['estilos'] ?? [];
                        $tags_str = '';
                        
                        if (!empty($generos_arr)) {
                             $tags_str .= '<strong>Gêneros:</strong> ' . implode(', ', $generos_arr) . '<br>';
                        }
                         if (!empty($estilos_arr)) {
                             $tags_str .= '<strong>Estilos:</strong> ' . implode(', ', $estilos_arr);
                        }
                        if (empty($generos_arr) && empty($estilos_arr)) {
                            $tags_str = 'N/A';
                        }
                        
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($album['titulo']); ?></strong>
                                <small style="display: block;">
                                    Lançamento: <?php echo formatar_data($album['data_lancamento']); ?>
                                </small>
                            </td>
                            
                            <td>
                                <?php echo htmlspecialchars($artistas_str); ?>
                                <?php 
                                    $produtores_display = $album['relacionamentos']['produtores'] ?? [];
                                    if (!empty($produtores_display)):
                                ?>
                                    <small style="display: block;">(Produtores: <?php echo implode(', ', $produtores_display); ?>)</small>
                                <?php endif; ?>
                            </td>

                            <td><?php echo $tags_str; ?></td>

                            <td>
                                <?php echo htmlspecialchars($album['formato_descricao'] ?? 'N/A'); ?>
                                <?php if (!empty($album['condicao'])): ?>
                                    <small style="display: block;">(Condição: <?php echo htmlspecialchars($album['condicao']); ?>)</small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php echo formatar_data($album['data_aquisicao']); ?>
                                <?php if ($album['preco'] !== null): ?>
                                    <small style="display: block;">(R$ <?php echo number_format($album['preco'], 2, ',', '.'); ?>)</small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="editar_colecao.php?id=<?php echo $album['id']; ?>" class="action-link">Editar</a> | 
                                <a href="excluir_colecao.php?id=<?php echo $album['id']; ?>" class="action-link delete-link" onclick="return confirm('Tem certeza que deseja remover este item da sua coleção?');">Remover</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>