<?php
// Arquivo: funcoes.php
// Contém funções reutilizáveis para a aplicação.

/**
 * Formata uma string de data (YYYY-MM-DD) para o formato brasileiro (DD/MM/YYYY).
 * Retorna 'N/D' se a data for inválida ou vazia.
 *
 * @param string|null $data A data no formato SQL (YYYY-MM-DD).
 * @return string A data formatada (DD/MM/YYYY) ou 'N/D'.
 */
function formatar_data(?string $data): string
{
    if (empty($data) || $data === '0000-00-00') {
        return 'N/D';
    }
    
    // Tenta criar um objeto DateTime a partir da string
    try {
        $date_obj = new DateTime($data);
        return $date_obj->format('d/m/Y');
    } catch (Exception $e) {
        // Retorna 'Data Inválida' em caso de erro de formatação/parsing
        return 'N/D';
    }
}

function formatar_data_log(?string $dataHora): string
{
    if (empty($dataHora) || $dataHora === '0000-00-00 00:00:00') {
        return 'N/A';
    }

    try {
         // Usa o formato H:i para incluir hora e minuto
        return date('d/m/Y H:i', strtotime($dataHora)); 
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Renderiza a tabela de álbuns e os links de paginação.
 * * ATUALIZADO: 
 * 1. Adicionado classes CSS para Dark Mode e layout responsivo.
 * 2. Atualizado referências de arquivo para 'store.php' (antigo index.php).
 * 3. Adicionado div de container para scroll horizontal da tabela.
 * * @param array $albuns Array de álbuns para exibir.
 * @param int $pagina_atual A página que está sendo exibida.
 * @param int $total_paginas O número total de páginas.
 * @param ?string $termo_busca O termo de busca atual.
 * @param ?int $artista_filtro O ID do artista selecionado.
 * @param ?int $tipo_filtro O ID do tipo de álbum.
 * @param ?int $situacao_filtro O ID da situação.
 * @param ?int $formato_filtro O ID do formato.
 * @param ?int $deletado_filtro O filtro de status de deleção (0, 1 ou -1).
 * @return void Imprime o HTML da tabela e da paginacao.
 */
function renderizar_tabela(
    array $albuns, 
    int $pagina_atual, 
    int $total_paginas, 
    ?string $termo_busca = '', 
    ?int $artista_filtro = null, 
    ?int $tipo_filtro = null, 
    ?int $situacao_filtro = null, 
    ?int $formato_filtro = null,
    ?int $deletado_filtro = 0 
): void {
    
    // Define o nome do arquivo atual para links de paginação e filtros
    // Se esta função for usada em colecao.php ou store.php, este valor deve ser dinâmico
    // Mas, assumindo que foi originalmente para index.php (agora store.php) ou colecao.php:
    // **NOTA:** Aqui, vamos assumir que está sendo usada no `store.php`, mas idealmente deveria
    // receber o nome do arquivo atual como argumento para ser reutilizável.
    // Para fins práticos da integração da Loja, usaremos 'store.php' como referência,
    // já que é o arquivo que acabamos de renomear.
    $base_file = 'store.php';

    if (empty($albuns)): ?>
        <p class="alerta">Nenhum álbum encontrado com os filtros aplicados.</p>
    <?php else: ?>
        
        <div class="album-table-container"> 
            <table class="album-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Artista</th>
                        <th>Lançamento</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Formato</th>
                        <th class="actions-cell">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($albuns as $album): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($album['id']); ?></td>
                            <td><?php echo htmlspecialchars($album['titulo'] ?? 'Sem Título'); ?></td>
                            <td><?php echo htmlspecialchars($album['nome_artista'] ?? 'Sem Artista'); ?></td>
                            <td><?php echo htmlspecialchars($album['data_lancamento'] ?? 'Sem Data'); ?></td>
                            <td><?php echo htmlspecialchars($album['tipo'] ?? 'Sem Tipo'); ?></td>
                            <td><?php echo htmlspecialchars($album['status'] ?? 'N/D'); ?></td>
                            <td><?php echo htmlspecialchars($album['formato'] ?? 'Sem Formato'); ?></td>
                            <td class="actions-cell">
                                <a href="editar_album.php?id=<?php echo $album['id']; ?>" title="Editar Álbum">
                                    <i class="fas fa-pencil-alt"></i>
                                </a>
                                
                                <?php if (($album['deletado'] ?? 0) == 1): ?>
                                    <a href="reativar.php?id=<?php echo $album['id']; ?>" 
                                        title="Reativar Álbum" 
                                        onclick="return confirm('Tem certeza que deseja reativar este álbum (ID: <?php echo $album['id']; ?>)?');">
                                        <i class="fas fa-redo"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="excluir.php?id=<?php echo $album['id']; ?>" 
                                        title="Excluir Álbum" 
                                        class="delete-link"
                                        onclick="return confirm('Tem certeza que deseja marcar este álbum (ID: <?php echo $album['id']; ?>) como excluído?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td> 
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div> <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php
                // Parâmetros de URL a serem mantidos (filtros)
                $query_params = '';
                if (!empty($termo_busca)) {
                    $query_params .= '&search_titulo=' . urlencode($termo_busca);
                }
                if ($artista_filtro) {
                    $query_params .= '&filter_artista=' . $artista_filtro;
                }
                if ($tipo_filtro) {
                    $query_params .= '&filter_tipo=' . $tipo_filtro;
                }
                if ($situacao_filtro) {
                    $query_params .= '&filter_situacao=' . $situacao_filtro;
                }
                if ($formato_filtro !== null) {
                    // Mantém o valor, inclusive -1
                    $query_params .= '&filter_formato=' . $formato_filtro;
                }
                
                // NOVO: Manter o filtro de deleção na paginação (se não for o padrão 0)
                // Se for -1 (Todos) ou 1 (Excluídos), precisamos manter
                if ($deletado_filtro != 0) { 
                    $query_params .= '&filter_deletado=' . $deletado_filtro;
                }
                
                // 1. Link para a página anterior
                if ($pagina_atual > 1): ?>
                    <a href="<?php echo $base_file; ?>?p=<?php echo $pagina_atual - 1; ?><?php echo $query_params; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="page-link disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <?php
                // 2. Links para as páginas (Exibe um bloco de 5 páginas ao redor da atual)
                $start = max(1, $pagina_atual - 2);
                $end = min($total_paginas, $pagina_atual + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?php echo $base_file; ?>?p=<?php echo $i; ?><?php echo $query_params; ?>" 
                        class="page-link <?php echo ($i == $pagina_atual) ? 'current-page' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php
                // 3. Link para a próxima página
                if ($pagina_atual < $total_paginas): ?>
                    <a href="<?php echo $base_file; ?>?p=<?php echo $pagina_atual + 1; ?><?php echo $query_params; ?>" class="page-link">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-link disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif;
}

    function fetchRelacionamentosM_N(PDO $pdo, $tabela_pivot, $tabela_dados, $coluna_ref, $id_ref) {
        if (!$id_ref) return [];

        // As colunas de ID no pivot devem ser: [colecao_id] e [id_da_outra_tabela]
        $coluna_outra_id = str_replace(['colecao_', 'colecao'], '', $tabela_pivot) . '_id';

        // Assume que a tabela de dados sempre tem as colunas 'id' e 'nome' ou 'descricao'
        $coluna_exibicao = in_array($tabela_dados, ['artistas', 'produtores', 'gravadoras']) ? 'nome' : 'descricao';

        $sql = "SELECT t.$coluna_exibicao, t.id 
                FROM $tabela_dados AS t
                JOIN $tabela_pivot AS tp ON t.id = tp.$coluna_outra_id
                WHERE tp.$coluna_ref = :id_ref
                ORDER BY t.$coluna_exibicao ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id_ref' => $id_ref]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Você pode querer logar o erro aqui em vez de morrer
            return [];
        }
    }

    /**
 * Limita o comprimento de uma string e adiciona reticências se for truncada.
 * @param string|null $texto O texto a ser limitado.
 * @param int $limite O número máximo de caracteres.
 * @return string O texto limitado.
 */
function limitar_texto(?string $texto, int $limite = 50): string
{
    if (empty($texto)) {
        return '';
    }

    // Garante que o texto é uma string antes de medir o comprimento
    $texto = (string) $texto; 

    if (strlen($texto) > $limite) {
        // Encontra a posição do último espaço antes do limite
        $posicao_corte = strrpos(substr($texto, 0, $limite), ' ');
        
        // Se houver um espaço para cortar limpo, corta. Senão, corta no limite.
        if ($posicao_corte !== false) {
            return trim(substr($texto, 0, $posicao_corte)) . '...';
        }
        
        // Corta direto se não houver espaço próximo ao limite
        return trim(substr($texto, 0, $limite)) . '...';
    }

    return $texto;
}

/**
 * Gera e exibe o bloco de links de paginação.
 *
 * @param int $pagina_atual A página atual sendo exibida.
 * @param int $total_paginas O número total de páginas.
 * @param string $link_base O link base com todos os filtros (sem o número da página).
 * @param int $max_links_exibidos O número máximo de links numéricos a mostrar (opcional).
 */
function renderizar_paginacao(int $pagina_atual, int $total_paginas, string $link_base, int $max_links_exibidos = 5)
{
    // Se houver apenas uma página, não renderiza nada.
    if ($total_paginas <= 1) {
        return;
    }

    $link_base = rtrim($link_base, '&'); // Remove '&' trailing se houver

    echo '<div class="pagination-container" style="display: flex; justify-content: center; align-items: center; margin-top: 30px; gap: 5px;">';

    // 1. LINK "ANTERIOR"
    if ($pagina_atual > 1) {
        $link_anterior = $link_base . '&p=' . ($pagina_atual - 1);
        echo '<a href="' . htmlspecialchars($link_anterior) . '" class="pagination-link btn-action" style="padding: 8px 15px; border-radius: 4px;"><i class="fas fa-chevron-left"></i> Anterior</a>';
    }

    // 2. LINKS NUMÉRICOS
    
    // Define o ponto de início e fim dos links numéricos a serem exibidos
    $inicio = max(1, $pagina_atual - floor($max_links_exibidos / 2));
    $fim = min($total_paginas, $inicio + $max_links_exibidos - 1);

    // Ajusta o início se o fim for o limite máximo
    if ($fim - $inicio + 1 < $max_links_exibidos) {
        $inicio = max(1, $fim - $max_links_exibidos + 1);
    }
    
    if ($inicio > 1) {
        echo '<span class="pagination-dots">...</span>';
    }

    for ($i = $inicio; $i <= $fim; $i++) {
        $link = $link_base . '&p=' . $i;
        $classe = ($i == $pagina_atual) ? 'current-page' : 'pagination-link';
        $style = ($i == $pagina_atual) ? 'background-color: var(--cor-principal); color: white; font-weight: bold;' : 'background-color: var(--cor-card); border: 1px solid var(--cor-borda);';

        echo '<a href="' . htmlspecialchars($link) . '" class="' . $classe . '" style="' . $style . ' padding: 8px 12px; border-radius: 4px; text-decoration: none;">' . $i . '</a>';
    }
    
    if ($fim < $total_paginas) {
        echo '<span class="pagination-dots">...</span>';
    }

    // 3. LINK "PRÓXIMO"
    if ($pagina_atual < $total_paginas) {
        $link_proximo = $link_base . '&p=' . ($pagina_atual + 1);
        echo '<a href="' . htmlspecialchars($link_proximo) . '" class="pagination-link btn-action" style="padding: 8px 15px; border-radius: 4px;">Próximo <i class="fas fa-chevron-right"></i></a>';
    }

    echo '</div>';
}

?>

