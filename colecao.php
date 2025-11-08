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
            c.capa_url, -- CORRIGIDO: Inclui a URL da capa
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
        'estilos' => 'colecao_estilo'
    ];
    
    $dados_mn = [];

    foreach ($relacionamentos as $nome_relacionamento => $tabela_relacao) {
        if (empty($colecao_ids)) break;

        // Determina a tabela auxiliar (artistas, produtores, generos, estilos)
        $tabela_aux = $nome_relacionamento; 
        
        // Determina o nome da coluna de ID (singular_nome + _id)
        $coluna_base_name = rtrim($nome_relacionamento, 's'); 
        if ($nome_relacionamento === 'produtores') {
             $coluna_base_name = 'produtor'; 
        }
        $coluna_id = $coluna_base_name . '_id'; 

        // Define a coluna de exibição e se usaremos 'nome' ou 'descricao'
        $coluna_display = 'nome';
        if ($nome_relacionamento == 'generos' || $nome_relacionamento == 'estilos') {
            $coluna_display = 'descricao';
        }
        
        // Query para buscar todas as associações de uma vez
        $sql_mn = "
            SELECT 
                cr.colecao_id, 
                t.{$coluna_display} AS descricao 
            FROM {$tabela_relacao} AS cr
            INNER JOIN {$tabela_aux} AS t ON cr.{$coluna_id} = t.id
            WHERE cr.colecao_id IN (" . implode(',', $colecao_ids) . ")
            ORDER BY cr.colecao_id, t.{$coluna_display} ASC";

        $stmt_mn = $pdo->query($sql_mn);
        $resultados = $stmt_mn->fetchAll(PDO::FETCH_ASSOC);

        // Organiza os resultados por colecao_id para injeção
        foreach ($resultados as $row) {
            $colecao_id = $row['colecao_id'];
            $descricao = $row['descricao'];
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
    <div class="main-layout">
        <div class="content-area">
            <h1 style="margin-bottom: 20px;">Sua Coleção Pessoal (Total: <?php echo count($colecao); ?> itens)</h1>
            <div class="colecao-list">
                <?php if (empty($colecao)): ?>
                    <p>Sua coleção está vazia. Adicione itens a partir do Catálogo!</p>
                <?php else: ?>
            
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Capa</th> <th>Título</th>
                                <th>Artistas</th>
                                <th>Gêneros/Estilos</th>
                                <th>Formato/Condição</th>
                                <th>Aquisição/Preço</th>
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
                                     $tags_str .= '<strong>Gêneros:</strong> ' . htmlspecialchars(implode(', ', $generos_arr)) . '<br>';
                                }
                                 if (!empty($estilos_arr)) {
                                     $tags_str .= '<strong>Estilos:</strong> ' . htmlspecialchars(implode(', ', $estilos_arr));
                                }
                                if (empty($generos_arr) && empty($estilos_arr)) {
                                    $tags_str = 'N/A';
                                }
            
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($album['capa_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($album['capa_url']); ?>"
                                                 alt="Capa de <?php echo htmlspecialchars($album['titulo']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background-color: #eee; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #666; border-radius: 3px;">
                                                S/ Capa
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($album['titulo']); ?></strong>
                                        <small style="display: block;">
                                            Lançamento: <?php echo formatar_data($album['data_lancamento']); ?>
                                        </small>
                                        <?php if (!empty($album['gravadora_nome'])): ?>
                                            <small style="display: block; font-weight: bold; color: #6c757d;">
                                                Gravadora: <?php echo htmlspecialchars($album['gravadora_nome']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
            
                                    <td>
                                        <?php echo htmlspecialchars($artistas_str); ?>
                                        <?php
                                            $produtores_display = $album['relacionamentos']['produtores'] ?? [];
                                            if (!empty($produtores_display)):
                                        ?>
                                            <small style="display: block;">(Produtores: <?php echo htmlspecialchars(implode(', ', $produtores_display)); ?>)</small>
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
                                        <a href="editar_colecao.php?id=<?php echo $album['id']; ?>" title="Editar item" class="action-icon edit">
                                            <i class="fa fa-pencil-alt"></i>
                                        </a>
            
                                        <a href="excluir_colecao.php?id=<?php echo $album['id']; ?>"
                                           title="Remover item (Exclusão Lógica)"
                                           class="action-icon delete"
                                           onclick="return confirm('Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?');">
                                            <i class="fa fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>