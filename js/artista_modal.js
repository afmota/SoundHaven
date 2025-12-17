document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('artistaModal');
    const loader = document.getElementById('artista-modal-loader');
    const target = document.getElementById('artista-modal-target');
    const closeModalBtn = modal.querySelector('.modal-close');

    document.addEventListener('click', async (e) => {
        const card = e.target.closest('.js-modal-artista');
        if (!card) return;

        const id = card.getAttribute('data-artista-id');
        abrirModalArtista(id);
    });

    async function abrirModalArtista(id) {
        modal.style.display = 'flex';
        loader.style.display = 'block';
        target.innerHTML = '';

        try {
            const response = await fetch(`fetch_artista_details.php?id=${id}`);
            const data = await response.json();

            if (data.success) {
                renderizarArtista(data.artista);
            } else {
                target.innerHTML = `<p>Erro ao carregar: ${data.message}</p>`;
            }
        } catch (error) {
            target.innerHTML = `<p>Erro de conexão.</p>`;
        } finally {
            loader.style.display = 'none';
        }
    }

    function renderizarArtista(art) {
    // Monta a galeria de álbuns
    let albunsHtml = '';
    if (art.albuns_colecao && art.albuns_colecao.length > 0) {
        albunsHtml = `
            <div class="modal-section">
                <h3>Álbuns na Coleção (${art.albuns_colecao.length})</h3>
                <div class="mini-discografia-grid">
                    ${art.albuns_colecao.map(album => `
                        <div class="mini-album-item">
                            <img src="${album.capa_url || '../img/default-cover.png'}" 
                                 title="${album.titulo}" 
                                 class="mini-capa">
                            <span class="mini-titulo">${album.titulo}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    target.innerHTML = `
        <div class="modal-grid">
            <div class="modal-col-capa">
                <img src="${art.imagem_url || '../img/default-artist.png'}" class="modal-capa">
                ${art.site_oficial ? `
                    <a href="${art.site_oficial}" target="_blank" class="btn-action secondary-action" style="width:100%; margin-top:15px; text-decoration:none; text-align:center; display:block;">
                        <i class="fas fa-external-link-alt"></i> Site Oficial
                    </a>
                ` : ''}
            </div>
            <div class="modal-col-details">
                <h2>${art.nome}</h2>
                <p class="modal-artista-destaque">${art.genero_nome || 'Gênero não definido'}</p>
                
                <div class="modal-info-meta">
                    <p><strong>País:</strong> ${art.pais_nome || 'N/D'}</p>
                    <p><strong>Início:</strong> ${art.data_inicio_pt || 'N/D'}</p>
                </div>

                <div class="modal-section">
                    <h3>Biografia</h3>
                    <div class="modal-obs" style="max-height: 200px; overflow-y: auto; margin-bottom: 20px;">
                        ${art.biografia_html}
                    </div>
                </div>

                ${albunsHtml} </div>
        </div>
        <div class="modal-footer" style="margin-top:20px; border-top: 1px solid #333; padding-top:15px;">
            <a href="editar_artista.php?id=${art.id}" class="btn-action primary-action"><i class="fas fa-edit"></i> Editar Artista</a>
        </div>
    `;
}

    const fechar = () => modal.style.display = 'none';
    closeModalBtn.onclick = fechar;
    window.onclick = (e) => { if (e.target == modal) fechar(); };
});