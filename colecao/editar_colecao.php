<?php
// Arquivo: editar_colecao.php
// Formulário e lógica para EDIÇÃO de um item na COLEÇÃO PESSOAL (tabela 'colecao').
// NOVIDADE: Suporte à edição de FAIXAS (Músicas).

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// Variáveis de status
$mensagem_status = '';
$tipo_mensagem = '';
$item = null;
$faixas_existentes = [];
$generos_existentes = [];
$estilos_existentes = [];

// Variável para armazenar o ID do item que está sendo editado (GET ou POST)
$colecao_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'colecao_id', FILTER_VALIDATE_INT);

// ----------------------------------------------------
// 1. CARREGAR DADOS DAS TABELAS DE APOIO (Listas para Dropdowns)
// ----------------------------------------------------
$listas = [];
$sqls = [
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
// 2. PROCESSAMENTO DO FORMULÁRIO (POST)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $colecao_id) {
    
    // Filtra e sanitiza todos os campos de entrada
    $input = filter_input_array(INPUT_POST, [
        'titulo' => FILTER_DEFAULT,
        'data_aquisicao' => FILTER_DEFAULT,
        'numero_catalogo' => FILTER_DEFAULT,
        'preco' => FILTER_VALIDATE_FLOAT,
        'condicao' => FILTER_DEFAULT,
        'observacoes' => FILTER_DEFAULT,
        'gravadora_id' => FILTER_VALIDATE_INT,
        'formato_id' => FILTER_VALIDATE_INT,
        'store_id' => FILTER_VALIDATE_INT,
        'ativo' => FILTER_VALIDATE_INT,
        'capa_url' => FILTER_DEFAULT,
    ]);

    // Lida com campos multi-select e faixas
    $generos_ids = $_POST['generos_ids'] ?? [];
    $estilos_ids = $_POST['estilos_ids'] ?? [];
    $faixas_data = $_POST['faixas'] ?? [];

    // Validação básica
    if (empty($input['titulo']) || empty($input['data_aquisicao'])) {
        $mensagem_status = "O título e a data de aquisição são obrigatórios.";
        $tipo_mensagem = 'erro';
    } else {
        
        $pdo->beginTransaction();
        try {
            // 2.1. ATUALIZAR TABELA 'colecao'
            $sql_colecao = "UPDATE colecao SET 
                                titulo = :titulo, 
                                data_lancamento = :data_lancamento, 
                                capa_url = :capa_url, 
                                data_aquisicao = :data_aquisicao, 
                                numero_catalogo = :numero_catalogo, 
                                preco = :preco, 
                                condicao = :condicao, 
                                observacoes = :observacoes, 
                                gravadora_id = :gravadora_id, 
                                formato_id = :formato_id, 
                                ativo = :ativo, 
                                store_id = :store_id,
                                atualizado_em = NOW()
                            WHERE id = :id";
                            
            $stmt_colecao = $pdo->prepare($sql_colecao);
            $stmt_colecao->execute([
                ':titulo' => $input['titulo'],
                ':data_lancamento' => $input['data_lancamento'] ?? null,
                ':capa_url' => $input['capa_url'],
                ':data_aquisicao' => $input['data_aquisicao'],
                ':numero_catalogo' => $input['numero_catalogo'],
                ':preco' => $input['preco'],
                ':condicao' => $input['condicao'],
                ':observacoes' => $input['observacoes'],
                ':gravadora_id' => $input['gravadora_id'],
                ':formato_id' => $input['formato_id'],
                ':ativo' => $input['ativo'] ?? 1,
                ':store_id' => $input['store_id'],
                ':id' => $colecao_id,
            ]);

            // 2.2. ATUALIZAR GÊNEROS E ESTILOS (DELETE + INSERT)
            $pdo->exec("DELETE FROM colecao_generos WHERE colecao_id = {$colecao_id}");
            $sql_genero = "INSERT INTO colecao_generos (colecao_id, genero_id) VALUES (:colecao_id, :genero_id)";
            $stmt_genero = $pdo->prepare($sql_genero);
            foreach ($generos_ids as $genero_id) {
                if (filter_var($genero_id, FILTER_VALIDATE_INT)) {
                    $stmt_genero->execute([':colecao_id' => $colecao_id, ':genero_id' => $genero_id]);
                }
            }
            
            $pdo->exec("DELETE FROM colecao_estilos WHERE colecao_id = {$colecao_id}");
            $sql_estilo = "INSERT INTO colecao_estilos (colecao_id, estilo_id) VALUES (:colecao_id, :estilo_id)";
            $stmt_estilo = $pdo->prepare($sql_estilo);
            foreach ($estilos_ids as $estilo_id) {
                if (filter_var($estilo_id, FILTER_VALIDATE_INT)) {
                    $stmt_estilo->execute([':colecao_id' => $colecao_id, ':estilo_id' => $estilo_id]);
                }
            }

            // 2.3. ATUALIZAR FAIXAS (DELETE ALL + INSERT ALL)
            $pdo->exec("DELETE FROM faixas_colecao WHERE colecao_id = {$colecao_id}");
            
            if (!empty($faixas_data)) {
                $sql_faixa = "INSERT INTO faixas_colecao (colecao_id, numero_faixa, titulo, duracao, audio_url) 
                              VALUES (:colecao_id, :numero_faixa, :titulo, :duracao, :audio_url)";
                $stmt_faixa = $pdo->prepare($sql_faixa);
                
                $faixa_num = 1; 

                foreach ($faixas_data as $faixa) {
                    $titulo = trim($faixa['titulo'] ?? '');
                    $duracao = trim($faixa['duracao'] ?? '');
                    $audio_url = trim($faixa['audio_url'] ?? '');
                    
                    if (!empty($titulo)) {
                        $stmt_faixa->execute([
                            ':colecao_id' => $colecao_id,
                            ':numero_faixa' => $faixa_num++,
                            ':titulo' => $titulo,
                            ':duracao' => $duracao,
                            ':audio_url' => $audio_url
                        ]);
                    }
                }
            }

            $pdo->commit();
            $mensagem_status = "Item #{$colecao_id} atualizado com sucesso!";
            $tipo_mensagem = 'sucesso';
            
            // Recarrega os dados após o sucesso para exibir o estado atualizado
            header("Location: editar_colecao.php?id={$colecao_id}&status=atualizado");
            exit();

        } catch (\PDOException $e) {
            $pdo->rollBack();
            $mensagem_status = "Erro ao atualizar item: " . $e->getMessage();
            $tipo_mensagem = 'erro';
            // Em caso de erro, os dados submetidos pelo POST ainda serão usados no formulário abaixo
        }
    }
}

// ----------------------------------------------------
// 3. CARREGAR DADOS DO ITEM (GET ou POST com erro)
// ----------------------------------------------------
if ($colecao_id) {
    try {
        // 3.1. Dados Principais
        $sql_item = "SELECT * FROM colecao WHERE id = :id";
        $stmt_item = $pdo->prepare($sql_item);
        $stmt_item->execute([':id' => $colecao_id]);
        $item = $stmt_item->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            $mensagem_status = "Item da Coleção não encontrado.";
            $tipo_mensagem = 'erro';
            $colecao_id = null; // Impede a renderização do formulário
        } else {
            // Se houve submissão POST com erro, $input já existe. Se não, use $item.
            $input = $input ?? $item;
        }

        // 3.2. Faixas Existentes
        $sql_faixas = "SELECT numero_faixa, titulo, duracao, audio_url 
                       FROM faixas_colecao 
                       WHERE colecao_id = :id 
                       ORDER BY numero_faixa ASC";
        $stmt_faixas = $pdo->prepare($sql_faixas);
        $stmt_faixas->execute([':id' => $colecao_id]);
        $faixas_existentes = $stmt_faixas->fetchAll(PDO::FETCH_ASSOC);

        // 3.3. Gêneros Existentes
        $sql_generos = "SELECT genero_id FROM colecao_generos WHERE colecao_id = :id";
        $stmt_generos = $pdo->prepare($sql_generos);
        $stmt_generos->execute([':id' => $colecao_id]);
        $generos_existentes = array_column($stmt_generos->fetchAll(PDO::FETCH_ASSOC), 'genero_id');
        
        // 3.4. Estilos Existentes
        $sql_estilos = "SELECT estilo_id FROM colecao_estilos WHERE colecao_id = :id";
        $stmt_estilos = $pdo->prepare($sql_estilos);
        $stmt_estilos->execute([':id' => $colecao_id]);
        $estilos_existentes = array_column($stmt_estilos->fetchAll(PDO::FETCH_ASSOC), 'estilo_id');
        
        // Se houve erro no POST, usamos os IDs submetidos, não os carregados do DB
        $generos_ids = $generos_ids ?? $generos_existentes;
        $estilos_ids = $estilos_ids ?? $estilos_existentes;
        $faixas_data = $faixas_data ?? $faixas_existentes;


    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao carregar dados do item: " . $e->getMessage();
        $tipo_mensagem = 'erro';
        $colecao_id = null;
    }
} else {
    $mensagem_status = "ID do item da Coleção não fornecido para edição.";
    $tipo_mensagem = 'erro';
}

require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1><i class="fas fa-edit"></i> Editar Item da Coleção #<?php echo htmlspecialchars($colecao_id ?? ''); ?></h1>
                <a href="detalhes_colecao.php?id=<?php echo htmlspecialchars($colecao_id ?? ''); ?>" class="back-link">
                    <i class="fas fa-chevron-left"></i> Ver Detalhes
                </a>
            </div>

            <?php if (isset($_GET['status']) && $_GET['status'] == 'atualizado'): ?>
                <p class="alerta sucesso">Item atualizado com sucesso!</p>
            <?php endif; ?>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>
            
            <?php if ($colecao_id): ?>
                <div class="card" style="margin-top: 20px;">
                    <form method="POST" action="editar_colecao.php" class="edit-form">
                        <input type="hidden" name="colecao_id" value="<?php echo htmlspecialchars($colecao_id); ?>">

                        <fieldset>
                            <legend>Informações do Álbum (Coleção)</legend>
                            
                            <label for="titulo">Título do Álbum (Coleção):</label>
                            <input type="text" id="titulo" name="titulo" required
                                    value="<?php echo htmlspecialchars($input['titulo'] ?? ''); ?>">

                            <label for="store_id">Referência do Catálogo (Store ID):</label>
                            <input type="number" id="store_id" name="store_id" placeholder="ID do álbum na tabela store (opcional)"
                                    value="<?php echo htmlspecialchars($input['store_id'] ?? ''); ?>">
                            <small>Ajuda a manter a consistência com o catálogo principal.</small>

                            <label for="capa_url">URL da Capa:</label>
                            <input type="text" id="capa_url" name="capa_url" 
                                    value="<?php echo htmlspecialchars($input['capa_url'] ?? ''); ?>">
                            
                            <label for="data_lancamento">Data de Lançamento (Orig.):</label>
                            <input type="date" id="data_lancamento" name="data_lancamento"
                                    value="<?php echo htmlspecialchars($input['data_lancamento'] ?? ''); ?>">

                        </fieldset>
                        
                        <fieldset style="margin-top: 25px;">
                            <legend>Detalhes da Sua Cópia</legend>
                            
                            <div class="field-row">
                                <div class="field-column">
                                    <label for="data_aquisicao">Data de Aquisição:</label>
                                    <input type="date" id="data_aquisicao" name="data_aquisicao" required
                                            value="<?php echo htmlspecialchars($input['data_aquisicao'] ?? ''); ?>">
                                </div>
                                <div class="field-column">
                                    <label for="preco">Preço (R$):</label>
                                    <input type="number" id="preco" name="preco" step="0.01" placeholder="0.00"
                                            value="<?php echo htmlspecialchars($input['preco'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="field-row">
                                <div class="field-column">
                                    <label for="numero_catalogo">Número de Catálogo:</label>
                                    <input type="text" id="numero_catalogo" name="numero_catalogo" placeholder="Número impresso no vinil/CD"
                                            value="<?php echo htmlspecialchars($input['numero_catalogo'] ?? ''); ?>">
                                </div>
                                <div class="field-column">
                                    <label for="condicao">Condição:</label>
                                    <input type="text" id="condicao" name="condicao" placeholder="Ex: Near Mint"
                                            value="<?php echo htmlspecialchars($input['condicao'] ?? ''); ?>">
                                </div>
                            </div>

                            <label for="observacoes">Observações:</label>
                            <textarea id="observacoes" name="observacoes" rows="4"><?php echo htmlspecialchars($input['observacoes'] ?? ''); ?></textarea>
                            
                            <label for="ativo" style="margin-top: 15px;">Status do Item:</label>
                            <select id="ativo" name="ativo">
                                <option value="1" <?php echo (isset($input['ativo']) && $input['ativo'] == 1) ? 'selected' : ''; ?>>1 - Ativo (Visível)</option>
                                <option value="0" <?php echo (isset($input['ativo']) && $input['ativo'] == 0) ? 'selected' : ''; ?>>0 - Excluído (Lógica)</option>
                            </select>
                            
                        </fieldset>

                        <fieldset style="margin-top: 25px;">
                            <legend>Metadados Adicionais</legend>

                            <div class="input-with-add-btn" id="add-gravadora-container">
                                <div class="input-with-select">
                                    <label for="gravadora_id">Gravadora:</label>
                                    <select id="gravadora_id" name="gravadora_id">
                                        <option value="">-- Selecione uma Gravadora --</option>
                                        <?php foreach ($listas['gravadoras'] as $g): ?>
                                            <option value="<?php echo $g['id']; ?>"
                                                    <?php echo (isset($input['gravadora_id']) && $input['gravadora_id'] == $g['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($g['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="button" class="add-button" data-type="gravadora" data-input-id="gravadora_id" data-url="../api/add_gravadora.php" title="Adicionar nova gravadora">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>

                            <label for="formato_id">Formato:</label>
                            <select id="formato_id" name="formato_id">
                                <option value="">-- Selecione um Formato --</option>
                                <?php foreach ($listas['formatos'] as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"
                                            <?php echo (isset($input['formato_id']) && $input['formato_id'] == $f['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($f['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <label for="generos_ids">Gêneros (Multi-select):</label>
                            <select id="generos_ids" name="generos_ids[]" multiple size="5">
                                <?php foreach ($listas['generos'] as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"
                                            <?php echo (in_array($g['id'], $generos_ids)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="estilos_ids">Estilos (Multi-select):</label>
                            <select id="estilos_ids" name="estilos_ids[]" multiple size="5">
                                <?php foreach ($listas['estilos'] as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"
                                            <?php echo (in_array($e['id'], $estilos_ids)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                        </fieldset>

                        <fieldset style="margin-top: 30px;">
                            <legend>Faixas do Álbum (Músicas e Áudio Preview)</legend>
                            
                            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                <button type="button" id="btn-adicionar-faixa" class="btn-adicionar-catalogo" style="flex-grow: 1;">
                                    <i class="fas fa-plus"></i> Adicionar Faixa Manualmente
                                </button>
                                <button type="button" id="btn-importar-faixas" class="btn-adicionar-catalogo secondary-action" style="flex-grow: 1; background-color: var(--cor-texto-secundario);" title="Funcionalidade a ser implementada">
                                    <i class="fas fa-folder-open"></i> Importar Faixas de Pasta (Pendente)
                                </button>
                            </div>
                            
                            <div id="faixas-container">
                                <?php 
                                $faixa_num = 1;
                                if (!empty($faixas_data)):
                                    foreach ($faixas_data as $faixa): 
                                        if (!empty($faixa['titulo'])):
                                            $titulo = htmlspecialchars($faixa['titulo']);
                                            $duracao = htmlspecialchars($faixa['duracao']);
                                            $audio_url = htmlspecialchars($faixa['audio_url']);
                                            
                                            // Renderiza a faixa existente (ou submetida com erro)
                                            echo '<div class="faixa-item card" data-faixa-num="'.$faixa_num.'" style="padding: 15px; margin-bottom: 10px; border-left: 3px solid var(--cor-destaque);">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                        <h4 style="margin: 0; font-size: 1em;">Faixa #'.$faixa_num.'</h4>
                                                        <button type="button" class="remove-faixa-btn secondary-action" style="background-color: var(--cor-borda); border: none; padding: 5px 10px; border-radius: 4px; color: var(--cor-texto-principal);">
                                                            <i class="fas fa-trash-alt"></i> Remover
                                                        </button>
                                                    </div>
                                                    
                                                    <label for="faixa_titulo_'.$faixa_num.'">Título:</label>
                                                    <input type="text" id="faixa_titulo_'.$faixa_num.'" name="faixas['.$faixa_num.'][titulo]" value="'.$titulo.'" required>
                                                    
                                                    <div class="field-row">
                                                        <div class="field-column" style="flex: 1;">
                                                            <label for="faixa_duracao_'.$faixa_num.'">Duração (MM:SS):</label>
                                                            <input type="text" id="faixa_duracao_'.$faixa_num.'" name="faixas['.$faixa_num.'][duracao]" value="'.$duracao.'" placeholder="Ex: 3:45">
                                                        </div>
                                                        <div class="field-column" style="flex: 2;">
                                                            <label for="faixa_url_'.$faixa_num.'">URL do Áudio Preview:</label>
                                                            <input type="text" id="faixa_url_'.$faixa_num.'" name="faixas['.$faixa_num.'][audio_url]" value="'.$audio_url.'" placeholder="Ex: /audio/album_id/01.mp3">
                                                            <small>Caminho do arquivo (MP3, FLAC) no servidor.</small>
                                                        </div>
                                                    </div>
                                                </div>';
                                        endif;
                                        $faixa_num++;
                                    endforeach;
                                else:
                                    echo '<p id="faixas-vazias" class="alerta" style="text-align: center;">Nenhuma faixa adicionada ainda.</p>';
                                endif;
                                ?>
                            </div>

                        </fieldset>

                        <div class="form-actions large-gap">
                            <a href="detalhes_colecao.php?id=<?php echo htmlspecialchars($colecao_id); ?>" class="back-link secondary-action">
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
// ==========================================
// 1. MANUSEIO DINÂMICO DE FAIXAS
// ==========================================

const faixasContainer = document.getElementById('faixas-container');
const btnAdicionarFaixa = document.getElementById('btn-adicionar-faixa');
const faixasVazias = document.getElementById('faixas-vazias');

// Calcula o próximo número de faixa baseado nas que já existem no HTML
let nextFaixaNum = 1;
if (faixasContainer.lastElementChild) {
    const lastFaixa = faixasContainer.lastElementChild;
    const lastNum = parseInt(lastFaixa.getAttribute('data-faixa-num'));
    if (!isNaN(lastNum)) {
        nextFaixaNum = lastNum + 1;
    }
}


// FUNÇÃO QUE CRIA O HTML DE UMA NOVA FAIXA
function createFaixaHtml(faixaNum, titulo = '', duracao = '', audio_url = '') {
    // O nome do campo usa 'faixas[...]' para ser submetido como um array
    return `
    <div class="faixa-item card" data-faixa-num="${faixaNum}" style="padding: 15px; margin-bottom: 10px; border-left: 3px solid var(--cor-destaque);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h4 style="margin: 0; font-size: 1em;">Faixa #${faixaNum}</h4>
            <button type="button" class="remove-faixa-btn secondary-action" style="background-color: var(--cor-borda); border: none; padding: 5px 10px; border-radius: 4px; color: var(--cor-texto-principal);">
                <i class="fas fa-trash-alt"></i> Remover
            </button>
        </div>
        
        <label for="faixa_titulo_${faixaNum}">Título:</label>
        <input type="text" id="faixa_titulo_${faixaNum}" name="faixas[${faixaNum}][titulo]" value="${titulo}" required>
        
        <div class="field-row">
            <div class="field-column" style="flex: 1;">
                <label for="faixa_duracao_${faixaNum}">Duração (MM:SS):</label>
                <input type="text" id="faixa_duracao_${faixaNum}" name="faixas[${faixaNum}][duracao]" value="${duracao}" placeholder="Ex: 3:45">
            </div>
            <div class="field-column" style="flex: 2;">
                <label for="faixa_url_${faixaNum}">URL do Áudio Preview:</label>
                <input type="text" id="faixa_url_${faixaNum}" name="faixas[${faixaNum}][audio_url]" value="${audio_url}" placeholder="Ex: /audio/album_id/01.mp3">
                <small>Caminho do arquivo (MP3, FLAC) no servidor.</small>
            </div>
        </div>
    </div>
    `;
}

// ADICIONAR NOVA FAIXA
btnAdicionarFaixa.addEventListener('click', () => {
    // Remove a mensagem de faixas vazias se existir
    const currentFaixasVazias = document.getElementById('faixas-vazias');
    if (currentFaixasVazias) {
        currentFaixasVazias.style.display = 'none';
    }
    
    // Adiciona o novo bloco HTML ao container
    faixasContainer.insertAdjacentHTML('beforeend', createFaixaHtml(nextFaixaNum));
    
    // Incrementa o contador para a próxima faixa
    nextFaixaNum++;
});

// REMOVER FAIXA (usando delegação de eventos no container)
faixasContainer.addEventListener('click', (event) => {
    // Verifica se o clique foi em um botão de remover
    if (event.target.closest('.remove-faixa-btn')) {
        const itemToRemove = event.target.closest('.faixa-item');
        if (itemToRemove) {
            itemToRemove.remove();
            
            // Reajusta a mensagem de faixa vazia, se necessário
            if (faixasContainer.children.length === 0) {
                 const currentFaixasVazias = document.getElementById('faixas-vazias');
                 if (currentFaixasVazias) {
                     currentFaixasVazias.style.display = 'block';
                 } else {
                     // Adiciona a mensagem de volta se ela foi removida
                     faixasContainer.insertAdjacentHTML('beforeend', '<p id="faixas-vazias" class="alerta" style="text-align: center;">Nenhuma faixa adicionada ainda.</p>');
                 }
            }
        }
    }
});


// ==========================================
// 2. MANUSEIO DE ADIÇÃO RÁPIDA DE METADADOS
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.input-with-add-btn').forEach(container => {
        const btn = container.querySelector('.add-button');
        const select = container.querySelector('select');
        const originalContent = btn.innerHTML; 

        btn.addEventListener('click', () => {
            // ... (Lógica AJAX de adição rápida de metadados, igual à do adicionar_colecao.php) ...
            // Este código é o mesmo do outro arquivo e foi omitido aqui para brevidade no template.
        });
    });
});

// ==========================================
// 3. MANUSEIO DE IMPORTAÇÃO DE FAIXAS POR PASTA
// ==========================================

const btnImportarFaixas = document.getElementById('btn-importar-faixas');

// AJUSTE: Remove o "(Pendente)" do texto do botão HTML para ficar pronto.
btnImportarFaixas.innerHTML = '<i class="fas fa-folder-open"></i> Importar Faixas de Pasta';

btnImportarFaixas.addEventListener('click', async () => {
    
    // No 'editar_colecao.php', o ID da coleção já existe; no 'adicionar_colecao.php' é nulo.
    const colecaoIdInput = document.querySelector('input[name="colecao_id"]');
    const colecao_id = colecaoIdInput ? colecaoIdInput.value : 0; // 0 é um ID dummy para criação
    
    // Pede o caminho da pasta via prompt
    const default_example = 'Exemplo: sepultura/roots_1996';
    const path = prompt(`Insira o caminho da pasta de áudio (relativo à sua pasta 'audio/'):\n${default_example}`);
    
    if (!path || path.trim() === '') {
        return; // Cancelado
    }
    
    // 1. Desabilita o botão e mostra o loading
    btnImportarFaixas.disabled = true;
    btnImportarFaixas.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
    
    // 2. Chamada AJAX
    try {
        const response = await fetch('../api/importar_faixas_pasta.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ colecao_id: colecao_id, folder_path: path.trim() })
        });

        const data = await response.json();

        if (response.ok && (data.status === 'sucesso' || data.status === 'alerta')) {
            
            // Se for sucesso, limpa e popula; se for alerta (faixas não encontradas), limpa.
            faixasContainer.innerHTML = '';
            nextFaixaNum = 1;

            if (data.faixas && data.faixas.length > 0) {
                // Adiciona as faixas importadas
                data.faixas.forEach(faixa => {
                    // A função createFaixaHtml foi definida no bloco PHP (usando os valores extraídos)
                    faixasContainer.insertAdjacentHTML('beforeend', createFaixaHtml(
                        nextFaixaNum, 
                        faixa.titulo, 
                        faixa.duracao, 
                        faixa.audio_url
                    ));
                    nextFaixaNum++;
                });

                alert('Importação concluída: ' + data.faixas.length + ' faixas encontradas e carregadas no formulário.');
                
            } else {
                 // Adiciona a mensagem de faixas vazias de volta
                 faixasContainer.insertAdjacentHTML('beforeend', '<p id="faixas-vazias" class="alerta" style="text-align: center;">Nenhuma faixa adicionada ainda.</p>');
                 alert(data.message); // Exibe a mensagem de alerta (Ex: "Nenhum arquivo encontrado")
            }
            
        } else {
            // Trata erros 400, 405, 500 etc.
            alert('Erro na importação: ' + (data.message || 'Erro desconhecido. Verifique o caminho da pasta e a instalação da getID3.'));
        }

    } catch (error) {
        console.error('Erro de rede:', error);
        alert('Erro de comunicação com o servidor durante a importação.');
    } finally {
        // 3. Restaura o botão
        btnImportarFaixas.disabled = false;
        btnImportarFaixas.innerHTML = '<i class="fas fa-folder-open"></i> Importar Faixas de Pasta';
    }
});
</script>

<?php require_once '../include/footer.php'; ?>