// Arquivo: js/store.js

function abrirModalDetalhes(id) {
    const modal = document.getElementById('modal-detalhes');
    const content = document.getElementById('modal-body-content');
    
    if (!modal || !content) return;

    // Mostra o modal (a classe 'active' deve ser controlada no CSS)
    modal.style.display = 'block';
    
    // Reset do conteúdo para o loading
    content.innerHTML = `
        <div style="text-align: center; padding: 50px;">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Carregando detalhes...</p>
        </div>`;
    
    fetch('detalhes_album.php?id=' + id)
        .then(response => {
            if (!response.ok) throw new Error('Erro na rede');
            return response.text();
        })
        .then(html => {
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<p class="alerta erro">Erro ao carregar os dados do álbum.</p>';
            console.error(err);
        });
}

function fecharModal() {
    const modal = document.getElementById('modal-detalhes');
    if (modal) modal.style.display = 'none';
}

function confirmarDescarte(id) {
    if(confirm('Tem certeza que deseja DESCARTAR este álbum? Ele sairá do catálogo.')) {
        window.location.href = 'deletar_album.php?id=' + id;
    }
}

// Fechar modal ao clicar fora ou na tecla ESC
window.addEventListener('click', function(event) {
    const modal = document.getElementById('modal-detalhes');
    if (event.target == modal) fecharModal();
});

document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") fecharModal();
});