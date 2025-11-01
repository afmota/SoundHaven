<?php
// Arquivo: colecao.php
// Listagem dos itens da Coleção Pessoal (tabela 'colecao').

require_once "../db/conexao.php";
require_once "../funcoes.php";

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
            c.capa_url, 
            f.descricao AS formato_descricao,
            g.nome AS gravadora_nome
        FROM colecao AS c
        LEFT JOIN formatos AS f ON c.formato_id = f.id
        LEFT JOIN gravadoras AS g ON c.gravadora_id = g.id
        WHERE c.ativo = 1 
        ORDER BY c.data_aquisicao DESC, c.titulo ASC";

    $stmt_colecao = $pdo->query($sql_colecao);
    $colecao = $stmt_colecao->fetchAll(PDO::FETCH_ASSOC);

    // Array para armazenar IDs para a busca M:N
    $colecao_ids = array_column($colecao, "id");

    // 2. Busca dos relacionamentos M:N (Agrupados para facilitar a exibição)
    $relacionamentos = [
        "artistas" => "colecao_artista",
        "produtores" => "colecao_produtor",
        "generos" => "colecao_genero",
        "estilos" => "colecao_estilo",
    ];

    $dados_mn = [];

    foreach ($relacionamentos as $nome_relacionamento => $tabela_relacao) {
        if (empty($colecao_ids)) {
            break;
        }

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

        // Query para buscar todas as associações de uma vez
        $sql_mn =
            "
            SELECT 
                cr.colecao_id, 
                t.{$coluna_display} AS descricao 
            FROM {$tabela_relacao} AS cr
            INNER JOIN {$tabela_aux} AS t ON cr.{$coluna_id} = t.id
            WHERE cr.colecao_id IN (" .
            implode(",", $colecao_ids) .
            ")
            ORDER BY cr.colecao_id, t.{$coluna_display} ASC";

        $stmt_mn = $pdo->query($sql_mn);
        $resultados = $stmt_mn->fetchAll(PDO::FETCH_ASSOC);

        // Organiza os resultados por colecao_id para injeção
        foreach ($resultados as $row) {
            $colecao_id = $row["colecao_id"];
            $descricao = $row["descricao"];
            $dados_mn[$colecao_id][$nome_relacionamento][] = $descricao;
        }
    }

    // Mescla os dados M:N na listagem principal
    foreach ($colecao as $key => $album) {
        $id = $album["id"];
        $colecao[$key]["relacionamentos"] = $dados_mn[$id] ?? [];
    }
} catch (\PDOException $e) {
    die("Erro ao carregar coleção: " . $e->getMessage());
}

// ----------------------------------------------------
// 2. HTML DA PÁGINA
// ----------------------------------------------------
require_once "../include/header.php";
?>

<div class="container">
    <div class="main-layout">
        <div class="content-area">
            <h1 style="margin-bottom: 20px;">Sua Coleção Pessoal (Total: <?php echo count(
                $colecao
            ); ?> itens)</h1>
            
            <div class="colecao-card-grid">
                <?php if (empty($colecao)): ?>
                    <div class="card" style="padding: 20px; text-align: center;">
                        <p style="margin: 0;">Sua coleção está vazia. Adicione itens a partir do Catálogo!</p>
                    </div>
                <?php else: ?>
                
                    <?php foreach ($colecao as $album):

                        $artistas_display = $album["relacionamentos"][
                            "artistas"
                        ] ?? ["N/A"];
                        $artistas_str = implode(
                            ", ",
                            $artistas_display
                        );
                        $generos_arr =
                            $album["relacionamentos"]["generos"] ?? [];
                        $estilos_arr =
                            $album["relacionamentos"]["estilos"] ?? [];
                        $tags_str = "";

                        if (!empty($generos_arr)) {
                            $tags_str .=
                                "<strong>Gêneros:</strong> " .
                                htmlspecialchars(
                                    implode(", ", $generos_arr)
                                );
                        }
                        if (!empty($estilos_arr)) {
                            if (!empty($generos_arr)) {
                                $tags_str .= " | ";
                            }
                            $tags_str .=
                                "<strong>Estilos:</strong> " .
                                htmlspecialchars(
                                    implode(", ", $estilos_arr)
                                );
                        }
                        if (
                            empty($generos_arr) &&
                            empty($estilos_arr)
                        ) {
                            $tags_str = "N/A";
                        }
                        ?>
                        
                        <div class="card colecao-item-card">
                            
                            <div class="card-header-main">
                                <div class="card-capa-wrapper">
                                    <?php if (!empty($album['capa_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($album['capa_url']); ?>"
                                            alt="Capa de <?php echo htmlspecialchars($album['titulo']); ?>"
                                            class="colecao-capa-grande"
                                            loading="lazy">
                                    <?php else: ?>
                                        <div class="colecao-capa-grande no-cover">S/ Capa</div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-details-main">
                                    <a href="detalhes_colecao.php?id=<?php echo $album["id"]; ?>" class="album-title-link">
                                        <h2><?php echo htmlspecialchars($album["titulo"]); ?></h2>
                                    </a>
                                    <p class="card-artista" style="color: var(--cor-destaque); font-weight: bold;"><?php echo htmlspecialchars($artistas_str); ?></p>
                                    <small style="display: block;">Lançamento: <?php echo formatar_data($album["data_lancamento"]); ?></small>
                                </div>
                            </div>

                            <div class="card-body-secondary">
                                <p class="card-tags"><small><?php echo $tags_str; ?></small></p>
                                
                                <div class="card-info-flex">
                                    <div class="info-block">
                                        <small style="color: var(--cor-texto-secundario);">Formato</small>
                                        <p><?php echo htmlspecialchars($album["formato_descricao"] ?? "N/A"); ?></p>
                                        <?php if (!empty($album["condicao"])): ?>
                                            <small style="color: #ffc107;">Condição: <?php echo htmlspecialchars($album["condicao"]); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="info-block">
                                        <small style="color: var(--cor-texto-secundario);">Aquisição</small>
                                        <p><?php echo formatar_data($album["data_aquisicao"]); ?></p>
                                        <?php if ($album["preco"] !== null): ?>
                                            <small style="color: #28a745;">R$ <?php echo number_format($album["preco"], 2, ",", "."); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer-actions">
                                <a href="editar_colecao.php?id=<?php echo $album["id"]; ?>" title="Editar item" class="action-icon edit">
                                    <i class="fa fa-pencil-alt" style="color: #007bff; cursor: pointer;"></i> Editar
                                </a>
                                <a href="excluir_colecao.php?id=<?php echo $album["id"]; ?>"
                                    title="Remover item (Exclusão Lógica)"
                                    class="action-icon delete"
                                    onclick="return confirm('Tem certeza que deseja REMOVER (Exclusão Lógica) este item da sua coleção?');">
                                    <i class="fa fa-trash-alt" style="color: #dc3545; cursor: pointer;"></i> Remover
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once "../include/footer.php"; ?>
