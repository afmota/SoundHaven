<?php
// Arquivo: funcoes.php
// Contém funções reutilizáveis para a aplicação.

/**
 * Renderiza a tabela de álbuns e os links de paginação.
 * Esta função deve ser chamada pelo index.php e pelo script AJAX.
 *
 * @param array $albuns Array de álbuns para exibir.
 * @param int $pagina_atual A página que está sendo exibida.
 * @param int $total_paginas O número total de páginas.
 * @param string $termo_busca O termo de busca atual (para manter nos links de paginacao).
 * @param int $artista_filtro O ID do artista selecionado (para manter nos links de paginacao).
 * @return void Imprime o HTML da tabela e da paginacao.
 */
function renderizar_tabela(array $albuns, int $pagina_atual, int $total_paginas, ?string $termo_busca = '', ?int $artista_filtro = null): void {
    if (empty($albuns)): ?>
        <p class="alerta">Nenhum álbum encontrado com os filtros aplicados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Artista</th>
                    <th>Lançamento</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Formato</th>
                    <th>Ações</th>
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
                        <td>
                            <a href="editar_album.php?id=<?php echo $album['id']; ?>" title="Editar Álbum">
                                <i class="fa-solid fa-pencil" style="color: #007bff; cursor: pointer;"></i>
                            </a>
                            <a href="excluir.php?id=<?php echo $album['id']; ?>" 
                                title="Excluir Álbum" 
                                onclick="return confirm('Tem certeza que deseja marcar este álbum (ID: <?php echo $album['id']; ?>) como excluído?');">
                                <i class="fa-solid fa-trash-can" style="color: #dc3545; cursor: pointer; margin-left: 8px;"></i>
                            </a>
                        </td> 
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_paginas > 1): ?>
            <div class="pagination">
                <?php
                // Parâmetros de URL a serem mantidos (termo de busca, artista)
                $query_params = '';
                if (!empty($termo_busca)) {
                    $query_params .= '&search_titulo=' . urlencode($termo_busca);
                }
                if ($artista_filtro) {
                    $query_params .= '&filter_artista=' . $artista_filtro;
                }
                
                // 1. Link para a página anterior
                if ($pagina_atual > 1): ?>
                    <a href="index.php?p=<?php echo $pagina_atual - 1; ?><?php echo $query_params; ?>" class="page-link">Anterior</a>
                <?php endif; ?>

                <?php
                // 2. Links para as páginas (Exibe um bloco de 5 páginas ao redor da atual)
                $start = max(1, $pagina_atual - 2);
                $end = min($total_paginas, $pagina_atual + 2);

                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="index.php?p=<?php echo $i; ?><?php echo $query_params; ?>" 
                       class="page-link <?php echo ($i == $pagina_atual) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php
                // 3. Link para a próxima página
                if ($pagina_atual < $total_paginas): ?>
                    <a href="index.php?p=<?php echo $pagina_atual + 1; ?><?php echo $query_params; ?>" class="page-link">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif;
}
?>