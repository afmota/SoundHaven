<?php
// Arquivo: adicionar_colecao.php
// Formulário e lógica para adicionar um item à COLEÇÃO PESSOAL (tabela 'colecao').
// SUPORTE TOTAL a Gêneros e Estilos separados.
// CORREÇÃO: Layout do formulário dividido em duas colunas lógicas (Metadados e Detalhes da Cópia).

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// ----------------------------------------------------
// 1. CARREGAR DADOS DAS TABELAS DE APOIO (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
    'artistas' => "SELECT id, nome FROM artistas ORDER BY nome ASC",
    'produtores' => "SELECT id, nome FROM produtores ORDER BY nome ASC",
    'gravadoras' => "SELECT id, nome FROM gravadoras ORDER BY nome ASC",
    'generos' => "SELECT id, descricao FROM generos ORDER BY descricao ASC",
    'formatos' => "SELECT id, descricao FROM formatos ORDER BY descricao ASC",
    'tipos' => "SELECT id, descricao FROM tipo_album ORDER BY descricao ASC",
    'estilos' => "SELECT id, descricao FROM estilos ORDER BY descricao ASC", 
];

try {
    foreach ($sqls as $nome => $sql) {
        $stmt = $pdo->query($sql);
        $listas[$nome] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\PDOException $e) {
    die("Erro ao carregar listas de apoio: " . $e->getMessage());
}

// ----------------------------------------------------
// 1.5. CARREGAR DADOS DO CATÁLOGO (STORE) SE HOUVER UM ID
// ----------------------------------------------------
$store_id = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
$dados_store = [];
$artistas_pre_selecionados = [];
$tipo_pre_selecionado = null;

if ($store_id) {
    try {
        // Buscando apenas os campos que existem na sua tabela 'store'
        $sql_store = "SELECT 
                        s.titulo, 
                        s.data_lancamento,
                        s.artista_id,
                        s.tipo_id,
                        s.formato_id
                    FROM store AS s 
                    WHERE s.id = :id";
        
        $stmt_store = $pdo->prepare($sql_store);
        $stmt_store->execute([':id' => $store_id]);
        $dados_store = $stmt_store->fetch(PDO::FETCH_ASSOC);

        if ($dados_store) {
            if ($dados_store['artista_id']) {
                $artistas_pre_selecionados = [$dados_store['artista_id']];
            }
            $tipo_pre_selecionado = $dados_store['tipo_id'];
        }

        if (!$dados_store) {
            $mensagem_status = "ID do álbum do Catálogo ({$store_id}) não encontrado.";
            $tipo_mensagem = 'erro';
            $store_id = null; 
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar dados do Catálogo: " . $e->getMessage();
        $tipo_mensagem = 'erro';
        $store_id = null;
    }
}


// ----------------------------------------------------
// 2. PROCESSAMENTO DO FORMULÁRIO (INSERT com Transação SQL)
// (Lógica de processamento omitida, pois não foi alterada, mas deve ser mantida)
// ----------------------------------------------------
$mensagem_status = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // 0. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS
        
        // Dados principais (Coleção)
        $titulo_input = filter_input(INPUT_POST, 'titulo', FILTER_DEFAULT);
        $titulo = html_entity_decode($titulo_input, ENT_QUOTES, 'UTF-8');
        
        $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
        $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null;
        $data_aquisicao = filter_input(INPUT_POST, 'data_aquisicao', FILTER_SANITIZE_SPECIAL_CHARS);
        $numero_catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        
        // Tratar vírgulas para decimais
        $preco_str = str_replace(',', '.', filter_input(INPUT_POST, 'preco', FILTER_DEFAULT));
        $preco = filter_var($preco_str, FILTER_VALIDATE_FLOAT) ?: null;
        
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        
        // Observações também precisam de decodificação para serem salvas puras.
        $observacoes_input = filter_input(INPUT_POST, 'observacoes', FILTER_DEFAULT);
        $observacoes = html_entity_decode($observacoes_input, ENT_QUOTES, 'UTF-8') ?: null;

        // IDs (Relacionamentos 1:N)
        $gravadora_id = filter_input(INPUT_POST, 'gravadora_id', FILTER_VALIDATE_INT) ?: null;
        $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);
        $store_id_ref = filter_input(INPUT_POST, 'store_id_ref', FILTER_VALIDATE_INT) ?: null;

        // IDs M:N (Recebidos como arrays)
        $artistas_ids = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $produtores_ids = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $generos_ids = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $estilos_ids = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []; 
        
        // Validação Mínima
        if (!$titulo || !$data_aquisicao || !$formato_id || empty($artistas_ids)) {
            throw new Exception("Campos obrigatórios (Título, Data de Aquisição, Formato, Artista) não preenchidos.");
        }
        
        // INÍCIO DA TRANSAÇÃO: Tudo ou nada.
        $pdo->beginTransaction();

        // 1. INSERT na tabela principal: 'colecao'
        $sql_colecao = "INSERT INTO colecao 
                        (titulo, data_lancamento, capa_url, data_aquisicao, 
                         numero_catalogo, preco, condicao, observacoes, 
                         gravadora_id, formato_id, store_id)
                        VALUES 
                        (:titulo, :data_lancamento, :capa_url, :data_aquisicao, 
                         :numero_catalogo, :preco, :condicao, :observacoes, 
                         :gravadora_id, :formato_id, :store_id)";
                         
        $stmt_colecao = $pdo->prepare($sql_colecao);
        $stmt_colecao->execute([
            ':titulo' => $titulo, 
            ':data_lancamento' => $data_lancamento ?: null,
            ':capa_url' => $capa_url,
            ':data_aquisicao' => $data_aquisicao,
            ':numero_catalogo' => $numero_catalogo,
            ':preco' => $preco,
            ':condicao' => $condicao,
            ':observacoes' => $observacoes, 
            ':gravadora_id' => $gravadora_id,
            ':formato_id' => $formato_id,
            ':store_id' => $store_id_ref,
        ]);

        // Obtém o ID da coleção recém-inserido
        $colecao_id = $pdo->lastInsertId();
        
        if (!$colecao_id) {
            throw new Exception("Falha ao obter o ID da coleção inserida.");
        }

        // Funções para Inserção de Relacionamentos (M:N)
        $insert_m_n = function($pdo, $table, $id_column, $ids_array) use ($colecao_id) {
            if (empty($ids_array)) return;
            $sql = "INSERT INTO {$table} (colecao_id, {$id_column}) VALUES (:cid, :id)";
            $stmt = $pdo->prepare($sql);
            foreach ($ids_array as $entity_id) {
                if (filter_var($entity_id, FILTER_VALIDATE_INT)) {
                    $stmt->execute([':cid' => $colecao_id, ':id' => $entity_id]);
                }
            }
        };

        // 2. INSERTS para Artistas (M:N)
        $insert_m_n($pdo, 'colecao_artista', 'artista_id', $artistas_ids);

        // 3. INSERTS para Produtores (M:N)
        $insert_m_n($pdo, 'colecao_produtor', 'produtor_id', $produtores_ids);

        // 4. INSERTS para Gêneros (M:N)
        $insert_m_n($pdo, 'colecao_genero', 'genero_id', $generos_ids);
        
        // 5. INSERTS para Estilos (M:N) 
        $insert_m_n($pdo, 'colecao_estilo', 'estilo_id', $estilos_ids);

        // FIM DA TRANSAÇÃO: Confirma todas as operações.
        $pdo->commit();
        
        // Usa o título puro na mensagem
        $mensagem_status = "Álbum '{$titulo}' adicionado à sua coleção (ID: {$colecao_id}) com sucesso!";
        $tipo_mensagem = 'sucesso';
        
        // Limpa o POST para não submeter novamente
        $_POST = []; 

    } catch (\PDOException $e) {
        $pdo->rollBack();
        $mensagem_status = "Erro ao salvar na coleção: Falha no banco de dados. " . $e->getMessage();
        $tipo_mensagem = 'erro';
    } catch (Exception $e) {
        $mensagem_status = "Erro de validação: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}


// ----------------------------------------------------
// 3. HTML DO FORMULÁRIO (Com campos de Adição Rápida)
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1>Adicionar Item à Sua Coleção</h1>
                <a href="/dashboard.php" class="back-link">
                    <i class="fas fa-chevron-left"></i> Voltar a Coleção
                </a>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>
            
            <div class="card">
                <p class="intro-text">Insira os metadados do álbum e os detalhes da sua cópia física. Dados do Catálogo (Loja) foram pré-carregados para facilitar.</p>

                <form method="POST" action="adicionar_colecao.php" class="edit-form">
                    
                    <?php if ($store_id): ?>
                        <input type="hidden" name="store_id_ref" value="<?php echo $store_id; ?>">
                    <?php endif; ?>
                    
                    <div class="colecao-grid">
                        
                        <fieldset>
                            <legend><i class="fas fa-compact-disc"></i> Metadados do Álbum</legend>

                            <label for="titulo">Título do Álbum:*</label>
                            <input type="text" id="titulo" name="titulo" required 
                                   value="<?php 
                                        $titulo_puro = html_entity_decode($dados_store['titulo'] ?? '', ENT_QUOTES, 'UTF-8');
                                        echo htmlspecialchars($titulo_puro); 
                                   ?>">
                            <small>Exemplo: Let's Dance</small>

                            <label for="artistas">Artista(s):*</label>
                            <div class="form-group-with-add">
                                <select id="artistas" name="artistas[]" multiple required style="min-height: 120px;">
                                    <?php $selecionados = $artistas_pre_selecionados; foreach ($listas['artistas'] as $artista): $is_selected = in_array($artista['id'], $selecionados); ?>
                                    <option value="<?php echo $artista['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($artista['nome']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="add-new-controls">
                                    <input type="text" id="artistas_novo_nome" placeholder="Novo Artista" class="small-input">
                                    <button type="button" class="btn-add-entity" data-target-id="artistas" data-table="artistas" data-input-id="artistas_novo_nome">
                                        <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <small>Use Ctrl (ou Cmd) para selecionar múltiplos artistas.</small>

                            <label for="produtores">Produtor(es):</label>
                            <div class="form-group-with-add">
                                <select id="produtores" name="produtores[]" multiple style="min-height: 120px;">
                                    <?php foreach ($listas['produtores'] as $produtor): ?>
                                    <option value="<?php echo $produtor['id']; ?>">
                                        <?php echo htmlspecialchars($produtor['nome']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="add-new-controls">
                                    <input type="text" id="produtores_novo_nome" placeholder="Novo Produtor" class="small-input">
                                    <button type="button" class="btn-add-entity" data-target-id="produtores" data-table="produtores" data-input-id="produtores_novo_nome">
                                        <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <small>Use Ctrl (ou Cmd) para selecionar múltiplos produtores.</small>

                            <label for="generos">Gênero(s) Principal(is):</label>
                            <div class="form-group-with-add">
                                <select id="generos" name="generos[]" multiple style="min-height: 120px;">
                                    <?php foreach ($listas['generos'] as $genero): ?>
                                    <option value="<?php echo $genero['id']; ?>">
                                        <?php echo htmlspecialchars($genero['descricao']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="add-new-controls">
                                    <input type="text" id="generos_novo_nome" placeholder="Novo Gênero" class="small-input">
                                    <button type="button" class="btn-add-entity" data-target-id="generos" data-table="generos" data-input-id="generos_novo_nome">
                                        <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <small>Gêneros **principais** (Ex: Rock, Jazz).</small>

                            <label for="estilos">Estilos/Subgêneros:</label>
                            <div class="form-group-with-add">
                                <select id="estilos" name="estilos[]" multiple style="min-height: 120px;">
                                    <?php foreach ($listas['estilos'] as $estilo): ?>
                                    <option value="<?php echo $estilo['id']; ?>">
                                        <?php echo htmlspecialchars($estilo['descricao']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="add-new-controls">
                                    <input type="text" id="estilos_novo_nome" placeholder="Novo Estilo" class="small-input">
                                    <button type="button" class="btn-add-entity" data-target-id="estilos" data-table="estilos" data-input-id="estilos_novo_nome">
                                        <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                    </button>
                                </div>
                            </div>
                            <small>Estilos **detalhados** (Ex: Post-Punk, Bossa Nova).</small>

                            <label for="data_lancamento">Data de Lançamento:</label>
                            <input type="date" id="data_lancamento" name="data_lancamento" value="<?php echo htmlspecialchars($dados_store['data_lancamento'] ?? ''); ?>">

                            <label for="gravadora_id">Gravadora:</label>
                            <div class="form-group-with-add">
                                <select id="gravadora_id" name="gravadora_id">
                                    <option value="">-- Selecione (Opcional) --</option>
                                    <?php foreach ($listas['gravadoras'] as $gravadora): ?>
                                    <option value="<?php echo $gravadora['id']; ?>">
                                        <?php echo htmlspecialchars($gravadora['nome']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="add-new-controls">
                                    <input type="text" id="gravadora_novo_nome" placeholder="Nova Gravadora" class="small-input">
                                    <button type="button" class="btn-add-entity" data-target-id="gravadora_id" data-table="gravadoras" data-input-id="gravadora_novo_nome">
                                        <i class="fas fa-plus add-icon"></i><i class="fas fa-check save-icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <label for="capa_url">URL da Capa:</label>
                            <input type="url" id="capa_url" name="capa_url" placeholder="https://..." value="<?php echo htmlspecialchars($_POST['capa_url'] ?? ''); ?>">

                        </fieldset>

                        <fieldset>
                            <legend><i class="fas fa-receipt"></i> Detalhes da Sua Cópia</legend>

                            <label for="data_aquisicao">Data de Aquisição:*</label>
                            <input type="date" id="data_aquisicao" name="data_aquisicao" required 
                                    value="<?php echo htmlspecialchars($_POST['data_aquisicao'] ?? date('Y-m-d')); ?>">

                            <label for="formato_id">Formato:*</label>
                            <select id="formato_id" name="formato_id" required>
                                <option value="">-- Selecione o Formato --</option>
                                <?php $formato_selecionado = $dados_store['formato_id'] ?? ($_POST['formato_id'] ?? null); ?>
                                <?php foreach ($listas['formatos'] as $formato): ?>
                                <option value="<?php echo htmlspecialchars($formato['id']); ?>" <?php echo ($formato_selecionado == $formato['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formato['descricao']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="numero_catalogo">Número de Catálogo:</label>
                            <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Ex: R-1234567" value="<?php echo htmlspecialchars($_POST['numero_catalogo'] ?? ''); ?>">

                            <label for="preco">Preço Pago:</label>
                            <input type="text" inputmode="decimal" id="preco" name="preco" placeholder="99,99" value="<?php echo htmlspecialchars($_POST['preco'] ?? ''); ?>" oninput="this.value = this.value.replace('.', ',')">
                            <small>Use vírgula para separar os centavos.</small>

                            <label for="condicao">Condição:</label>
                            <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint" value="<?php echo htmlspecialchars($_POST['condicao'] ?? ''); ?>">

                            <label for="observacoes">Observações:</label>
                            <textarea id="observacoes" name="observacoes" rows="8"><?php 
                                $obs_default = '';
                                if ($store_id) {
                                    $tipo_descricao = '';
                                    foreach($listas['tipos'] as $tipo) {
                                        if ($tipo['id'] == $tipo_pre_selecionado) {
                                            $tipo_descricao = $tipo['descricao'];
                                            break;
                                        }
                                    }
                                    $obs_default = "Tipo de Álbum Original (Catálogo): " . ($tipo_descricao ?: 'Não Informado') . "\n";
                                }
                                echo htmlspecialchars(html_entity_decode($_POST['observacoes'] ?? $obs_default, ENT_QUOTES, 'UTF-8')); 
                            ?></textarea>
                            <small>O campo "Tipo" original do Catálogo foi adicionado aqui para referência.</small>
                            
                        </fieldset>

                    </div> <div class="form-actions large-gap">
                        <a href="/dashboard.php" class="back-link secondary-action">
                            <i class="fas fa-times-circle"></i> Cancelar e Voltar
                        </a>
                        <button type="submit" class="save-button">
                            <i class="fas fa-check-double"></i> Mover para Coleção
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<script> 
    // ... CÓDIGO JAVASCRIPT PARA 'btn-add-entity' (MANTIDO) ... 
    document.addEventListener('DOMContentLoaded', () => { 
        // Seleciona todos os botões de Adição Rápida 
        const buttons = document.querySelectorAll('.btn-add-entity'); 
        const endpoint = 'add_entity_ajax.php'; 
        buttons.forEach(button => { 
            button.addEventListener('click', (e) => {
                const btn = e.currentTarget;
                const container = btn.closest('.form-group-with-add');
                const targetId = btn.getAttribute('data-target-id');
                const table = btn.getAttribute('data-table');
                const inputId = btn.getAttribute('data-input-id');
                const input = document.getElementById(inputId);
                const value = input.value.trim();
                const select = document.getElementById(targetId);
                const isMultiple = select.hasAttribute('multiple');
                const originalContent = btn.innerHTML;

                if (!value) {
                    alert('Por favor, insira um nome.');
                    return;
                }

                // MODO: ATIVAR ADIÇÃO (Desativar e mostrar loader)
                container.classList.add('adding-mode');
                btn.disabled = true;
                input.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table: table, nome: value })
                })
                .then(response => {
                    const status = response.status;
                    return response.json().then(body => ({ status, body }));
                })
                .then(({ status, body }) => {
                    if (status === 201) {
                        // 1. Cria nova opção
                        const newOption = document.createElement('option');
                        newOption.value = body.id;
                        newOption.textContent = body.value;
                        
                        // 2. Adiciona ao select
                        select.appendChild(newOption);
                        
                        // 3. Seleciona a nova opção
                        if (isMultiple) {
                            // Para multi-select (Artistas, Produtores, Gêneros, Estilos)
                            newOption.selected = true; 
                        } else {
                            // Para single-select (Gravadora)
                            select.value = body.id; 
                        }
                        
                        // 4. Feedback e Limpeza
                        alert(`Sucesso! "${body.value}" adicionado.`);
                        input.value = '';

                    } else if (status === 409) {
                        alert(body.message + ' Tente selecionar na lista.');
                        input.value = '';
                    } else {
                        alert('Erro ao adicionar: ' + (body.message || 'Erro de comunicação.'));
                    }
                })
                .catch(error => {
                    console.error('Erro de rede:', error);
                    alert('Erro de rede ou servidor ao tentar salvar.');
                })
                .finally(() => {
                    // MODO: DESATIVAR ADIÇÃO (Voltar ao estado original)
                    container.classList.remove('adding-mode');
                    btn.disabled = false;
                    input.disabled = false;
                    btn.innerHTML = originalContent; 
                });
            }
        });
    });
</script>

<?php require_once '../include/footer.php'; ?>