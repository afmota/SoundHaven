<?php
// Arquivo: index.php
// Listagem de álbuns com Paginação e Filtros via Submissão GET (Sidebar Layout)

require_once 'conexao.php';
require_once 'funcoes.php'; // Contém a função renderizar_tabela()

// --- CONFIGURAÇÃO DE PAGINAÇÃO E FILTROS ---
$limite_por_pagina = 25; 
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1; 

// Pega e sanitiza os filtros da URL
$termo_busca = filter_input(INPUT_GET, 'search_titulo', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$artista_filtro = filter_input(INPUT_GET, 'filter_artista', FILTER_VALIDATE_INT);
$tipo_filtro = filter_input(INPUT_GET, 'filter_tipo', FILTER_VALIDATE_INT);
$situacao_filtro = filter_input(INPUT_GET, 'filter_situacao', FILTER_VALIDATE_INT);
$formato_filtro = filter_input(INPUT_GET, 'filter_formato', FILTER_VALIDATE_INT);

// Inicializa arrays para a cláusula WHERE e para os parâmetros de segurança do PDO
$where_condicoes = ['s.deletado = 0']; // Condição base
$bind_params = [];

// Adiciona filtros
if (!empty($termo_busca)) {
    $where_condicoes[] = "s.titulo LIKE :titulo";
    $bind_params[':titulo'] = '%' . $termo_busca . '%';
}
if ($artista_filtro) {
    $where_condicoes[] = "s.artista_id = :artista_id";
    $bind_params[':artista_id'] = $artista_filtro;
}
if ($tipo_filtro) {
    $where_condicoes[] = "s.tipo_id = :tipo_id";
    $bind_params[':tipo_id'] = $tipo_filtro;
}
if ($situacao_filtro) {
    $where_condicoes[] = "s.situacao = :situacao_id";
    $bind_params[':situacao_id'] = $situacao_filtro;
}
if ($formato_filtro) {
    if ($formato_filtro == -1) { // -1 significa 'Sem Formato' (NULL)
        $where_condicoes[] = "s.formato_id IS NULL";
    } elseif ($formato_filtro > 0) {
        $where_condicoes[] = "s.formato_id = :formato_id";
        $bind_params[':formato_id'] = $formato_filtro;
    }
}

// Lógica de reset de página ao aplicar novo filtro
$has_filter = !empty($termo_busca) || $artista_filtro || $tipo_filtro || $situacao_filtro || $formato_filtro;
if ($has_filter && !isset($_GET['p'])) {
    $pagina_atual = 1;
}
if ($pagina_atual < 1) {
    $pagina_atual = 1;
}
$offset = ($pagina_atual - 1) * $limite_por_pagina;


// --- CONSULTA 1: NÚMERO TOTAL DE REGISTROS (COM FILTROS) ---
$sql_total = "SELECT COUNT(s.id) AS total FROM store AS s WHERE " . implode(' AND ', $where_condicoes);
$total_registros = 0;

try {
    $stmt_total = $pdo->prepare($sql_total);
    foreach ($bind_params as $param => $value) {
        $type = (strpos($param, 'id') !== false) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt_total->bindParam($param, $bind_params[$param], $type);
    }
    
    $stmt_total->execute();
    $resultado_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resultado_total['total'];
    
} catch (\PDOException $e) {
    $erro_total = "Erro ao contar registros: " . $e->getMessage();
}

$total_paginas = ceil($total_registros / $limite_por_pagina);


// --- CONSULTA 2: BUSCA DADOS RELACIONADOS (Dropdowns) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$sql_tipos = "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC";
$sql_situacao = "SELECT id, descricao FROM situacao ORDER BY descricao ASC";
$sql_formatos = "SELECT id, descricao FROM formatos ORDER BY descricao ASC";
$artistas = $tipos = $situacoes = $formatos = [];

try {
    $stmt_artistas = $pdo->query($sql_artistas); $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);
    $stmt_tipos = $pdo->query($sql_tipos); $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
    $stmt_situacao = $pdo->query($sql_situacao); $situacoes = $stmt_situacao->fetchAll(PDO::FETCH_ASSOC);
    $stmt_formatos = $pdo->query($sql_formatos); $formatos = $stmt_formatos->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    // Tratamento de erro silencioso
}


// --- CONSULTA 3: BUSCA A LISTAGEM PRINCIPAL ---

$sql = "SELECT
            s.id, 
            s.titulo, 
            a.nome AS nome_artista,
            DATE_FORMAT(s.data_lancamento, '%d/%m/%Y') AS data_lancamento, 
            t.descricao AS tipo,
            sit.descricao AS status,
            f.descricao AS formato
        FROM store AS s
            LEFT JOIN artistas AS a ON s.artista_id = a.id 
            LEFT JOIN tipo_album AS t ON s.tipo_id = t.id
            LEFT JOIN situacao AS sit ON s.situacao = sit.id
            LEFT JOIN formatos AS f ON s.formato_id = f.id
        WHERE " . implode(' AND ', $where_condicoes) . "
        ORDER BY s.data_lancamento DESC
        LIMIT :limite OFFSET :offset"; 

try {
    $stmt = $pdo->prepare($sql); 
    
    // Bind de LIMIT e OFFSET
    $stmt->bindParam(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    // Bind dos parâmetros de filtro
    foreach ($bind_params as $param => $value) {
        $type = (strpos($param, 'id') !== false) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindParam($param, $bind_params[$param], $type);
    }
    
    $stmt->execute();
    $albuns = $stmt->fetchAll();

} catch (\PDOException $e) {
    $erro = "Erro ao buscar álbuns: " . $e->getMessage();
    $albuns = []; 
}

// INÍCIO DO HTML
require_once 'header.php'; 
?>

<div class="container">
    <div class="main-layout"> 
        
        <main class="content-area">
            
            <h1>Listagem de Álbuns (Total: <?php echo $total_registros; ?>)</h1>

            <?php 
            // Mensagens de Status (Criação, Edição, Exclusão)
            if (isset($_GET['status']) && $_GET['status'] == 'editado'): 
            ?>
                <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album']); ?>" atualizado com sucesso!</p>
            <?php 
            elseif (isset($_GET['status']) && $_GET['status'] == 'criado'): 
            ?>
                <p class="sucesso">Álbum "<?php echo htmlspecialchars($_GET['album']); ?>" adicionado com sucesso!</p>
            <?php 
            elseif (isset($_GET['status']) && $_GET['status'] == 'excluido'): 
            ?>
                <p class="sucesso">Álbum excluído logicamente com sucesso.</p>
            <?php 
            elseif (isset($_GET['status']) && strpos($_GET['status'], 'erro') !== false): 
            ?>
                <p class="erro">Erro ao processar a operação. Tente novamente.</p>
            <?php endif; ?>

            <?php 
            // CORREÇÃO: Passando todos os filtros para a função
            renderizar_tabela($albuns, $pagina_atual, $total_paginas, $termo_busca, $artista_filtro, $tipo_filtro, $situacao_filtro, $formato_filtro);
            ?>

        </main>
        
        <aside class="sidebar-filters">

            <h3>Filtros de Busca</h3>
            
            <form method="GET" action="index.php" class="filters-container">
                
                <div class="search-container">
                    <label for="search_titulo">Buscar Título:</label>
                    <input type="text" id="search_titulo" name="search_titulo" 
                           value="<?php echo htmlspecialchars($termo_busca); ?>">
                </div>

                <div class="search-container">
                    <label for="filter_artista">Artista:</label>
                    <select id="filter_artista" name="filter_artista">
                        <option value="">-- Todos --</option>
                        <?php foreach ($artistas as $artista): ?>
                            <option value="<?php echo htmlspecialchars($artista['id']); ?>"
                                    <?php echo ($artista_filtro == $artista['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($artista['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-container">
                    <label for="filter_tipo">Tipo:</label>
                    <select id="filter_tipo" name="filter_tipo">
                        <option value="">-- Todos --</option>
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?php echo htmlspecialchars($tipo['id']); ?>"
                                    <?php echo ($tipo_filtro == $tipo['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['descricao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-container">
                    <label for="filter_situacao">Situação:</label>
                    <select id="filter_situacao" name="filter_situacao">
                        <option value="">-- Todas --</option>
                        <?php foreach ($situacoes as $situacao): ?>
                            <option value="<?php echo htmlspecialchars($situacao['id']); ?>"
                                    <?php echo ($situacao_filtro == $situacao['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($situacao['descricao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-container">
                    <label for="filter_formato">Formato:</label>
                    <select id="filter_formato" name="filter_formato">
                        <option value="">-- Todos --</option>
                        <option value="-1" <?php echo ($formato_filtro == -1) ? 'selected' : ''; ?>>
                            Sem Formato
                        </option>
                        <?php foreach ($formatos as $formato): ?>
                            <option value="<?php echo htmlspecialchars($formato['id']); ?>"
                                    <?php echo ($formato_filtro == $formato['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formato['descricao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="save-button" style="height: 40px; background-color: #007bff; flex-shrink: 0;">Aplicar Filtros</button>
                    <?php if ($has_filter): ?>
                        <a href="index.php" class="back-link" style="height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">Limpar Filtros</a>
                    <?php endif; ?>
                </div>
                
            </form>

        </aside>

    </div> </div> <?php
require_once 'footer.php';