<?php
// Arquivo: index.php
// Listagem de álbuns com Paginação e Filtros via Submissão GET (Simples e Performático)

require_once 'conexao.php';
require_once 'funcoes.php'; // Contém a função renderizar_tabela()

// --- CONFIGURAÇÃO DE PAGINAÇÃO E FILTROS ---
$limite_por_pagina = 25; 
$pagina_atual = isset($_GET['p']) ? (int)$_GET['p'] : 1; 

// Pega e sanitiza os filtros da URL
$termo_busca = filter_input(INPUT_GET, 'search_titulo', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''; // <-- NOVO: Usa o operador '??' (null coalescing)
$artista_filtro = filter_input(INPUT_GET, 'filter_artista', FILTER_VALIDATE_INT);

// Inicializa arrays para a cláusula WHERE e para os parâmetros de segurança do PDO
$where_condicoes = ['s.deletado = 0']; // Condição base: mostrar apenas não deletados
$bind_params = [];

// Adiciona filtro por Título
if (!empty($termo_busca)) {
    $where_condicoes[] = "s.titulo LIKE :titulo";
    $bind_params[':titulo'] = '%' . $termo_busca . '%';
}

// Adiciona filtro por Artista
if ($artista_filtro) {
    $where_condicoes[] = "s.artista_id = :artista_id";
    $bind_params[':artista_id'] = $artista_filtro;
}

// Se algum filtro foi aplicado, garantimos que a página atual seja 1
// (Apenas se não houver um 'p' na URL, para não atrapalhar a navegação já filtrada)
if ((!empty($termo_busca) || $artista_filtro) && !isset($_GET['p'])) {
    $pagina_atual = 1;
}
if ($pagina_atual < 1) {
    $pagina_atual = 1;
}

// Calcula o OFFSET (o ponto de partida no banco)
$offset = ($pagina_atual - 1) * $limite_por_pagina;


// --- CONSULTA 1: BUSCA O NÚMERO TOTAL DE REGISTROS (COM OS FILTROS APLICADOS) ---
$sql_total = "SELECT COUNT(s.id) AS total FROM store AS s WHERE " . implode(' AND ', $where_condicoes);
$total_registros = 0;

try {
    $stmt_total = $pdo->prepare($sql_total);
    // Bind dos parâmetros para a contagem total
    foreach ($bind_params as $param => $value) {
        $type = (strpos($param, 'artista') !== false) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt_total->bindParam($param, $bind_params[$param], $type);
    }
    
    $stmt_total->execute();
    $resultado_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_registros = $resultado_total['total'];
    
} catch (\PDOException $e) {
    $erro_total = "Erro ao contar registros: " . $e->getMessage();
}

// Calcula o número total de páginas
$total_paginas = ceil($total_registros / $limite_por_pagina);


// --- CONSULTA 2: BUSCA TODOS OS ARTISTAS (para popular o dropdown) ---
$sql_artistas = "SELECT id, nome FROM artistas ORDER BY nome ASC";
$artistas = [];
try {
    $stmt_artistas = $pdo->query($sql_artistas); 
    $artistas = $stmt_artistas->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $erro_artistas = "Erro ao buscar artistas: " . $e->getMessage();
}


// --- CONSULTA 3: BUSCA A LISTAGEM PRINCIPAL (COM LIMIT, OFFSET e FILTROS) ---

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
    
    // Bind dos parâmetros LIMIT e OFFSET
    $stmt->bindParam(':limite', $limite_por_pagina, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    // Bind de todos os parâmetros de filtro (novamente, por segurança)
    foreach ($bind_params as $param => $value) {
        $type = (strpos($param, 'artista') !== false) ? PDO::PARAM_INT : PDO::PARAM_STR;
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

    <form method="GET" action="index.php" class="filters-container">
        
        <div class="search-container">
            <label for="search_titulo">Buscar Título do Álbum:</label>
            <input type="text" id="search_titulo" name="search_titulo" 
                   placeholder="Digite o título do álbum..." autocomplete="off"
                   value="<?php echo htmlspecialchars($termo_busca); ?>">
        </div>

        <div class="search-container">
            <label for="filter_artista">Filtrar por Artista:</label>
            <select id="filter_artista" name="filter_artista">
                <option value="">-- Selecione um Artista --</option>
                <?php if (!empty($artistas)): ?>
                    <?php foreach ($artistas as $artista): ?>
                        <option value="<?php echo htmlspecialchars($artista['id']); ?>"
                                <?php echo ($artista_filtro == $artista['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($artista['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        
        <button type="submit" class="save-button" style="margin-top: 25px; height: 40px; background-color: #007bff;">Aplicar Filtros</button>
        
        <?php if (!empty($termo_busca) || $artista_filtro): ?>
            <a href="index.php" class="back-link" style="margin-top: 25px; height: 40px; display: flex; align-items: center; justify-content: center;">Limpar Filtros</a>
        <?php endif; ?>
        
    </form>

    <?php 
    renderizar_tabela($albuns, $pagina_atual, $total_paginas, $termo_busca, $artista_filtro);
    ?>

<?php
// FIM DO HTML
require_once 'footer.php';