document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('albumModal');
    const loader = document.getElementById('modal-loader');
    const target = document.getElementById('modal-details-target');
    const closeModalBtn = document.querySelector('.modal-close');

    // 1. Delegar o clique nos cards
    let isFetching = false; // Trava para evitar cliques duplos

    document.addEventListener('click', async (e) => {
        const card = e.target.closest('.js-modal-colecao');
        if (!card || isFetching) return; // Se já estiver buscando, não faz nada

        isFetching = true; // Ativa a trava
        const albumId = card.getAttribute('data-album-id');

        try {
            await abrirModal(albumId);
        } finally {
            isFetching = false; // Libera a trava depois que terminar
        }
    });

    // 2. Função para buscar e preencher os dados
    async function abrirModal(id) {
        modal.style.display = 'flex';
        loader.style.display = 'block';
        target.innerHTML = ''; // Limpa conteúdo anterior

        try {
            const response = await fetch(`fetch_album_details.php?id=${id}`);
            const data = await response.json();

            if (data.success) {
                renderizarDetalhes(data.album);
            } else {
                target.innerHTML = `<p class="erro">Erro: ${data.message}</p>`;
            }
        } catch (error) {
            target.innerHTML = `<p class="erro">Erro de conexão com o servidor.</p>`;
        } finally {
            loader.style.display = 'none';
        }
    }

    // 3. Função para montar o HTML dentro do modal
    function renderizarDetalhes(album) {
        const artistas = album.relacionamentos.artistas.join(', ') || 'Vários';
        const generos = album.relacionamentos.generos.join(', ') || '-';
        
        let tracklistHtml = album.faixas.map(f => `
            <li>
                <span class="track-num">${f.numero_faixa}.</span>
                <span class="track-title">${f.titulo}</span>
                <span class="track-dur">${f.duracao || ''}</span>
            </li>
        `).join('');

        target.innerHTML = `
            <div class="modal-grid">
                <div class="modal-col-capa">
                    <img src="${album.capa_url || '../img/default-cover.png'}" class="modal-capa">
                </div>
                <div class="modal-col-details">
                    <h2>${album.titulo}</h2>
                    <p class="modal-artista-destaque">${artistas}</p>
                    
                    <div class="modal-info-meta">
                        <p><strong>Lançamento:</strong> ${album.data_lancamento_pt}</p>
                        <p><strong>Gravadora:</strong> ${album.gravadora_nome || 'N/D'}</p>
                        <p><strong>Gêneros:</strong> ${generos}</p>
                    </div>

                    <div class="modal-section">
                        <h3>Dados da Cópia</h3>
                        <div class="info-grid">
                            <span><strong>Formato:</strong> ${album.formato_descricao}</span>
                            <span><strong>Aquisição:</strong> ${album.data_aquisicao_pt}</span>
                            <span><strong>Preço:</strong> ${album.preco_formatado}</span>
                            <span><strong>Condição:</strong> ${album.condicao || 'N/D'}</span>
                        </div>
                    </div>

                    <div class="modal-section">
                        <h3>Lista de Faixas</h3>
                        <ul class="modal-tracklist">${tracklistHtml || '<li>Nenhuma faixa cadastrada.</li>'}</ul>
                    </div>

                    ${album.observacoes ? `
                        <div class="modal-section">
                            <h3>Observações</h3>
                            <p class="modal-obs">${album.observacoes}</p>
                        </div>
                    ` : ''}
                </div>
            </div>
            <div class="modal-footer">
                <a href="editar_colecao.php?id=${album.id}" class="btn-action primary-action"><i class="fas fa-edit"></i> Editar</a>
                <button onclick="window.print()" class="btn-action secondary-action"><i class="fas fa-print"></i> Imprimir</button>
            </div>
        `;
    }

    // 4. Fechar Modal
    const fechar = () => modal.style.display = 'none';
    closeModalBtn.onclick = fechar;
    window.onclick = (e) => { if (e.target == modal) fechar(); };
});