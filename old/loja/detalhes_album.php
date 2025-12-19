<?php
// Arquivo: detalhes_album.php
// Visualiza os detalhes de um álbum específico no Catálogo (tabela 'store').

require_once '../db/conexao.php';
require_once '../funcoes.php'; 

// ----------------------------------------------------
// 1. OBTENÇÃO DO ID E BUSCA DOS DADOS
// ----------------------------------------------------
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$album = null;
$mensagem_status = '';
$tipo_mensagem = '';

if (!$id) {
    $mensagem_status = "ID do álbum não fornecido.";
    $tipo_mensagem = 'erro';
} else {
    try {
        // SQL com JOINs para obter todos os detalhes de uma vez
        $sql = "SELECT 
                    s.id, s.titulo, s.data_lancamento, s.criado_em, s.atualizado_em,
                    a.nome AS nome_artista,
                    ta.descricao AS descricao_tipo,
                    st.descricao AS descricao_situacao,
                    f.descricao AS descricao_formato,
                    s.situacao AS situacao_id 
                FROM store AS s
                LEFT JOIN artistas AS a ON s.artista_id = a.id
                LEFT JOIN tipo_album AS ta ON s.tipo_id = ta.id
                LEFT JOIN situacao AS st ON s.situacao = st.id
                LEFT JOIN formatos AS f ON s.formato_id = f.id
                WHERE s.id = :id AND s.deletado = 0";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $album = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$album) {
            $mensagem_status = "Álbum não encontrado ou foi excluído do Catálogo.";
            $tipo_mensagem = 'erro';
        }

    } catch (\PDOException $e) {
        $mensagem_status = "Erro ao buscar dados do álbum: " . $e->getMessage();
        $tipo_mensagem = 'erro';
    }
}

// ----------------------------------------------------
// 2. HTML DA PÁGINA
// ----------------------------------------------------
require_once '../include/header.php'; 
?>

<div class="container" style="padding-top: 100px;">
    <div class="main-layout"> 
        
        <main class="content-area full-width">
            
            <div class="page-header-actions">
                <h1>
                    <?php echo $album ? 'Detalhes: ' . htmlspecialchars($album['titulo']) : 'Detalhes do Álbum'; ?>
                </h1>
                
                <div class="header-buttons">
                    <?php if ($album): ?>
                        
                        <a href="editar_album.php?id=<?php echo $album['id']; ?>" class="btn-action edit-button">
                            <i class="fas fa-edit"></i> Editar
                        </a>

                        <button type="button" class="btn-action delete-button" 
                            onclick="if(confirm('Tem certeza que deseja desativar este álbum do catálogo?')) { window.location.href = 'deletar_album.php?id=<?php echo $album['id']; ?>'; }">
                            <i class="fas fa-trash-alt"></i> Desativar
                        </button>
                        
                    <?php endif; ?>

                    <a href="store.php" class="back-link secondary-action">
                        <i class="fas fa-chevron-left"></i> Voltar ao Catálogo
                    </a>
                </div>
            </div>

            <?php if (!empty($mensagem_status)): ?>
                <p class="alerta <?php echo $tipo_mensagem; ?>"><?php echo $mensagem_status; ?></p>
            <?php endif; ?>

            <?php if ($album): ?>
                
                <div class="details-grid">
                        
                    <div class="card details-panel-info"> <h2><i class="fas fa-info-circle"></i> Informações do Álbum</h2>
                        
                        <div class="detail-item">
                            <span class="label">Título:</span>
                            <span class="value main-title"><?php echo htmlspecialchars($album['titulo']); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="label">Artista:</span>
                            <span class="value"><?php echo htmlspecialchars($album['nome_artista'] ?? 'N/A'); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="label">Data de Lançamento:</span>
                            <span class="value"><?php echo formatar_data_log(htmlspecialchars($album['data_lancamento'])); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="label">Tipo:</span>
                            <span class="value"><?php echo htmlspecialchars($album['descricao_tipo'] ?? 'N/A'); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="label">Formato:</span>
                            <span class="value"><?php echo htmlspecialchars($album['descricao_formato'] ?? 'N/A'); ?></span>
                        </div>

                    </div> <div class="card details-panel-status"> <h2><i class="fas fa-signal"></i> Status e Log</h2>

                        <div class="detail-item">
                            <span class="label">Situação Atual:</span>
                            <span class="value status-<?php echo getStatusClass($album['situacao_id']); ?>">
                                <?php echo htmlspecialchars($album['descricao_situacao']); ?>
                            </span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="label">ID no Catálogo:</span>
                            <span class="value"><?php echo $album['id']; ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="label">Criado em:</span>
                            <span class="value date-log"><?php echo formatar_data_log(htmlspecialchars($album['criado_em'])); ?></span>
                        </div>

                        <div class="detail-item">
                            <span class="label">Última Atualização:</span>
                            <span class="value date-log"><?php echo formatar_data_log(htmlspecialchars($album['atualizado_em'] ?? '')); ?></span>
                        </div>
                        
                        </div> </div> <?php 
                // O ID 4 é o "Adquirido", conforme vimos no editar_album.php
                if ($album['situacao_id'] != 4): 
                ?>
                    <div style="text-align: right; margin-top: 30px;">
                        <a href="../colecao/adicionar_colecao.php?store_id=<?php echo $album['id']; ?>" class="btn-action primary-action">
                            <i class="fas fa-sign-out-alt"></i> Mover para Coleção
                        </a>
                    </div>
                <?php endif; ?>
                <?php endif; ?>

        </main>
    </div>
</div> <?php

// Retorna uma classe CSS baseada no ID da situação para coloração
function getStatusClass($situacao_id) {
    switch ($situacao_id) {
        case 1: return 'wishlist'; // Ex: Amarelo
        case 2: return 'buscando'; // Ex: Azul
        case 3: return 'pendente'; // Ex: Laranja
        case 4: return 'adquirido'; // Ex: Verde
        default: return 'default';
    }
}

require_once '../include/footer.php';
?>