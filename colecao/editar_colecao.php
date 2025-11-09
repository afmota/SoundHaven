<?php
// Arquivo: editar_colecao.php
// Formulário e lógica para EDIÇÃO de um item na COLEÇÃO PESSOAL (tabela 'colecao').
// VERSÃO SIMPLIFICADA (SEM SUPORTE A FAIXAS/MÚSICAS).
// CORREÇÃO: Tratamento robusto para caracteres especiais e aspas.

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';
$item = null;
$artistas_existentes = [];
$produtores_existentes = [];
$generos_existentes = [];
$estilos_existentes = [];

// Variável para armazenar o ID do item que está sendo editado (GET ou POST)
$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT);

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
// 2. PROCESSAMENTO DO FORMULÁRIO (UPDATE com Transação SQL)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $colecao_id) {
    
    try {
        // 0. SANITIZAÇÃO E VALIDAÇÃO DOS DADOS
        
        // CORREÇÃO: Decodifica o TÍTULO antes de enviar ao banco.
        $titulo_input = filter_input(INPUT_POST, 'titulo', FILTER_DEFAULT);
        $titulo = html_entity_decode($titulo_input, ENT_QUOTES, 'UTF-8');
        
        $data_lancamento = filter_input(INPUT_POST, 'data_lancamento', FILTER_SANITIZE_SPECIAL_CHARS);
        $capa_url = filter_input(INPUT_POST, 'capa_url', FILTER_VALIDATE_URL) ?: null;
        $data_aquisicao = filter_input(INPUT_POST, 'data_aquisicao', FILTER_SANITIZE_SPECIAL_CHARS);
        $numero_catalogo = filter_input(INPUT_POST, 'numero_catalogo', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        
        $preco_str = str_replace(',', '.', filter_input(INPUT_POST, 'preco', FILTER_DEFAULT));
        $preco = filter_var($preco_str, FILTER_VALIDATE_FLOAT) ?: null;
        
        $condicao = filter_input(INPUT_POST, 'condicao', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
        
        // CORREÇÃO: Decodifica OBSERVAÇÕES antes de enviar ao banco.
        $observacoes_input = filter_input(INPUT_POST, 'observacoes', FILTER_DEFAULT);
        $observacoes = html_entity_decode($observacoes_input, ENT_QUOTES, 'UTF-8') ?: null;

        $gravadora_id = filter_input(INPUT_POST, 'gravadora_id', FILTER_VALIDATE_INT) ?: null;
        $formato_id = filter_input(INPUT_POST, 'formato_id', FILTER_VALIDATE_INT);

        $artistas_ids = filter_input(INPUT_POST, 'artistas', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $produtores_ids = filter_input(INPUT_POST, 'produtores', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $generos_ids = filter_input(INPUT_POST, 'generos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
        $estilos_ids = filter_input(INPUT_POST, 'estilos', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? []; 
        
        
        if (!$titulo || !$data_aquisicao || !$formato_id || empty($artistas_ids)) {
            throw new Exception("Campos obrigatórios (Título, Data de Aquisição, Formato, Artista) não preenchidos.");
        }
        
        // INÍCIO DA TRANSAÇÃO: Tudo ou nada.
        $pdo->beginTransaction();

        // 1. UPDATE na tabela principal: 'colecao'
        $sql_colecao = "UPDATE colecao SET 
                        titulo = :titulo, data_lancamento = :data_lancamento, capa_url = :capa_url, 
                        data_aquisicao = :data_aquisicao, numero_catalogo = :numero_catalogo, 
                        preco = :preco, condicao = :condicao, observacoes = :observacoes, 
                        gravadora_id = :gravadora_id, formato_id = :formato_id
                        WHERE id = :id";
                         
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
            ':id' => $colecao_id,
        ]);

        // Função para Limpar e Re-Inserir Relacionamentos (M:N)
        $insert_m_n = function($pdo, $table, $id_column, $ids_array) use ($colecao_id) {
            // Limpar todos os relacionamentos existentes
            $pdo->prepare("DELETE FROM {$table} WHERE colecao_id = :cid")->execute([':cid' => $colecao_id]);
            
            if (empty($ids_array)) return;
            
            // Re-inserir os novos (ou os mesmos)
            $sql = "INSERT INTO {$table} (colecao_id, {$id_column}) VALUES (:cid, :id)";
            $stmt = $pdo->prepare($sql);
            foreach ($ids_array as $entity_id) {
                if (filter_var($entity_id, FILTER_VALIDATE_INT)) {
                    $stmt->execute([':cid' => $colecao_id, ':id' => $entity_id]);
                }
            }
        };

        // 2. Limpar e Re-Inserir Artistas (M:N)
        $insert_m_n($pdo, 'colecao_artista', 'artista_id', $artistas_ids);

        // 3. Limpar e Re-Inserir Produtores (M:N)
        $insert_m_n($pdo, 'colecao_produtor', 'produtor_id', $produtores_ids);

        // 4. Limpar e Re-Inserir Gêneros (M:N)
        $insert_m_n($pdo, 'colecao_genero', 'genero_id', $generos_ids);
        
        // 5. Limpar e Re-Inserir Estilos (M:N) 
        $insert_m_n($pdo, 'colecao_estilo', 'estilo_id', $estilos_ids);
        

        // FIM DA TRANSAÇÃO: Confirma todas as operações.
        $pdo->commit();
        
        $mensagem_status = "Álbum '{$titulo}' atualizado na sua coleção com sucesso!";
        $tipo_mensagem = 'sucesso';
        
        // Redireciona para evitar re-submit e força recarregamento dos dados
        header("Location: editar_colecao.php?id={$colecao_id}&status=sucesso&msg=" . urlencode($mensagem_status));
        exit();

    } catch (\PDOException $e) {
        $pdo->rollBack();
        $mensagem_status = "Erro ao atualizar no banco de dados: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    } catch (Exception $e) {
        $mensagem_status = "Erro de validação: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// ----------------------------------------------------
// 3. CARREGAR DADOS EXISTENTES PARA PREENCHIMENTO DO FORMULÁRIO (GET)
// ----------------------------------------------------
if ($colecao_id) {
    try {
        // SQL para obter os dados principais
        $sql = "SELECT * FROM colecao WHERE id = :id AND ativo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $colecao_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $mensagem_status = "Item da coleção ID {$colecao_id} não encontrado.";
            $tipo_mensagem = 'erro';
            $colecao_id = null;
        } else {
            // Buscar relacionamentos M:N
            $artistas_existentes = $pdo->query("SELECT artista_id FROM colecao_artista WHERE colecao_id = {$colecao_id}")->fetchAll(PDO::FETCH_COLUMN, 0);
            $produtores_existentes = $pdo->query("SELECT produtor_id FROM colecao_produtor WHERE colecao_id = {$colecao_id}")->fetchAll(PDO::FETCH_COLUMN, 0);
            $generos_existentes = $pdo->query("SELECT genero_id FROM colecao_genero WHERE colecao_id = {$colecao_id}")->fetchAll(PDO::FETCH_COLUMN, 0);
            $estilos_existentes = $pdo->query("SELECT estilo_id FROM colecao_estilo WHERE colecao_id = {$colecao_id}")->fetchAll(PDO::FETCH_COLUMN, 0);
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar dados: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// Trata o status da URL após o redirecionamento
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $tipo_mensagem = $_GET['status'];
    $mensagem_status = urldecode($_GET['msg']);
}

// ----------------------------------------------------
// 4. HTML DO FORMULÁRIO
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<style>
.form-row-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px; /* Espaço entre as colunas */
}
.form-row-2-col > div {
    /* Garante que o input/select fique agrupado com o label */
    display: flex;
    flex-direction: column;
}
.form-row-2-col > div > label {
    margin-bottom: 5px;
}
</style>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1><?php echo $colecao_id ? 'Editar Item da Coleção (ID: ' . $colecao_id . ')' : 'Erro: ID não encontrado'; ?></h1>
                <?php if ($colecao_id): ?>
                    <a href="/colecao/detalhes_colecao.php?id=<?php echo $colecao_id; ?>" class="back-link">
                        <i class="fas fa-chevron-left"></i> Voltar aos Detalhes
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>
            
            <?php if ($item): ?>
                <div class="card">
                    <p class="intro-text">Atualize os metadados do álbum e os detalhes da sua cópia física.</p>

                    <form method="POST" action="editar_colecao.php" class="edit-form">
                        
                        <input type="hidden" name="colecao_id" value="<?php echo $colecao_id; ?>">
                        
                        <div class="colecao-grid">
                            
                            <fieldset>
                                <legend><i class="fas fa-compact-disc"></i> Metadados do Álbum</legend>

                                <label for="titulo">Título do Álbum:*</label>
                                <input type="text" id="titulo" name="titulo" required 
                                    value="<?php echo htmlspecialchars(html_entity_decode($item['titulo'], ENT_QUOTES, 'UTF-8')); ?>">

                                <label for="artistas">Artista(s):*</label>
                                <div class="form-group-with-add">
                                    <select id="artistas" name="artistas[]" multiple required style="min-height: 120px;">
                                        <?php $selecionados = $artistas_existentes; foreach ($listas['artistas'] as $artista): $is_selected = in_array($artista['id'], $selecionados); ?>
                                        <option value="<?php echo $artista['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(html_entity_decode($artista['nome'], ENT_QUOTES, 'UTF-8')); ?>
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
                                        <?php $selecionados = $produtores_existentes; foreach ($listas['produtores'] as $produtor): $is_selected = in_array($produtor['id'], $selecionados); ?>
                                        <option value="<?php echo $produtor['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(html_entity_decode($produtor['nome'], ENT_QUOTES, 'UTF-8')); ?>
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
                                        <?php $selecionados = $generos_existentes; foreach ($listas['generos'] as $genero): $is_selected = in_array($genero['id'], $selecionados); ?>
                                        <option value="<?php echo $genero['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(html_entity_decode($genero['descricao'], ENT_QUOTES, 'UTF-8')); ?>
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
                                        <?php $selecionados = $estilos_existentes; foreach ($listas['estilos'] as $estilo): $is_selected = in_array($estilo['id'], $selecionados); ?>
                                        <option value="<?php echo $estilo['id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(html_entity_decode($estilo['descricao'], ENT_QUOTES, 'UTF-8')); ?>
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

                                <label for="gravadora_id">Gravadora:</label>
                                <div class="form-group-with-add">
                                    <select id="gravadora_id" name="gravadora_id">
                                        <option value="">-- Selecione (Opcional) --</option>
                                        <?php $selecionado = $item['gravadora_id']; foreach ($listas['gravadoras'] as $gravadora): ?>
                                        <option value="<?php echo $gravadora['id']; ?>" <?php echo ($selecionado == $gravadora['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(html_entity_decode($gravadora['nome'], ENT_QUOTES, 'UTF-8')); ?>
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
                                
                                <div class="form-row-2-col">
                                    <div>
                                        <label for="data_lancamento">Data de Lançamento:</label>
                                        <input type="date" id="data_lancamento" name="data_lancamento" value="<?php echo htmlspecialchars($item['data_lancamento'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label for="capa_url">URL da Capa:</label>
                                        <input type="url" id="capa_url" name="capa_url" placeholder="https://..." value="<?php echo htmlspecialchars($item['capa_url'] ?? ''); ?>">
                                    </div>
                                </div>

                            </fieldset>

                            <fieldset>
                                <legend><i class="fas fa-receipt"></i> Detalhes da Sua Cópia</legend>
                                
                                <div class="form-row-2-col">
                                    <div>
                                        <label for="data_aquisicao">Data de Aquisição:*</label>
                                        <input type="date" id="data_aquisicao" name="data_aquisicao" required 
                                                value="<?php echo htmlspecialchars($item['data_aquisicao'] ?? date('Y-m-d')); ?>">
                                    </div>
                                    <div>
                                        <label for="formato_id">Formato:*</label>
                                        <select id="formato_id" name="formato_id" required>
                                            <option value="">-- Selecione o Formato --</option>
                                            <?php $formato_selecionado = $item['formato_id']; foreach ($listas['formatos'] as $formato): ?>
                                            <option value="<?php echo htmlspecialchars($formato['id']); ?>" <?php echo ($formato_selecionado == $formato['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($formato['descricao']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row-2-col">
                                    <div>
                                        <label for="numero_catalogo">Número de Catálogo:</label>
                                        <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Ex: R-1234567" value="<?php echo htmlspecialchars($item['numero_catalogo'] ?? ''); ?>">
                                    </div>
                                    <div>
                                        <label for="preco">Preço Pago:</label>
                                        <input type="text" inputmode="decimal" id="preco" name="preco" placeholder="99,99" value="<?php echo htmlspecialchars(str_replace('.', ',', $item['preco'] ?? '')); ?>" oninput="this.value = this.value.replace('.', ',')">
                                        <small>Use vírgula para separar os centavos.</small>
                                    </div>
                                </div>

                                <label for="condicao">Condição:</label>
                                <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint" value="<?php echo htmlspecialchars($item['condicao'] ?? ''); ?>">

                                <label for="observacoes">Observações:</label>
                                <textarea id="observacoes" name="observacoes" rows="8"><?php echo htmlspecialchars(html_entity_decode($item['observacoes'] ?? '', ENT_QUOTES, 'UTF-8')); ?></textarea>
                                <small>Use este campo para anotações pessoais sobre a cópia.</small>
                                
                            </fieldset>

                        </div> <div class="form-actions large-gap">
                            <a href="/colecao/detalhes_colecao.php?id=<?php echo htmlspecialchars($colecao_id); ?>" class="back-link secondary-action">
                                <i class="fas fa-times-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="save-button">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script> 
    // -------------------------------------------------------------------------------------
    // Inicialização do Formulário: Carregar Listeners (Lógica de Adição Rápida)
    // -------------------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => { 
        
        // Lógica de Adição Rápida (Mantida, pois estava funcionando)
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
                        // O valor retornado pelo PHP (body.value) deve ser o nome já decodificado/limpo.
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
            });
        });
    });
</script>

<?php require_once '../include/footer.php'; ?>