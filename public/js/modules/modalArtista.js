// /public/js/modules/modalArtista.js
import * as api from './api.js';
import * as u from './utils.js';

const modalSelector = '#artistaModal';

function $(sel) { return document.querySelector(sel); }

function openModalArtista() {
  const modal = $(modalSelector);
  if (!modal) return;
  modal.style.display = 'flex';
  modal.querySelector('.modal-content')?.classList.remove('loaded');
  const loader = $('#modal-loader-artista');
  const details = $('#modal-details-artista');
  if (loader) loader.style.display = '';
  if (details) details.style.display = 'none';
}

function closeModalArtista() {
  const modal = $(modalSelector);
  if (!modal) return;
  modal.style.display = 'none';
  $('#modal-details-artista')?.innerHTML = '';
  $('#modal-actions-artista')?.innerHTML = '';
}

async function fetchArtistaDetails(artistaId) {
  if (!artistaId) return;
  openModalArtista();
  try {
    const html = await api.fetchArtistHtml(artistaId);
    const details = document.getElementById('modal-details-artista');
    if (details) {
      details.innerHTML = html; // confia no servidor (era html no sistema original)
      setTimeout(() => {
        details.style.display = '';
        document.getElementById('modal-loader-artista').style.display = 'none';
        document.querySelector('#artistaModal .modal-content')?.classList.add('loaded');
      }, 50);
    }
  } catch (err) {
    const details = document.getElementById('modal-details-artista');
    if (details) {
      details.innerHTML = '<p class="alert alert-danger">Erro ao carregar detalhes do artista. Tente novamente.</p>';
      details.style.display = '';
      document.getElementById('modal-loader-artista').style.display = 'none';
      document.querySelector('#artistaModal .modal-content')?.classList.add('loaded');
    }
    console.error(err);
  }
}

function init() {
  // abrir via clique delegado
  document.addEventListener('click', (e) => {
    const card = e.target.closest('.open-modal-artista');
    if (!card) return;
    e.preventDefault();
    const artistaId = card.dataset.artistaId;
    fetchArtistaDetails(artistaId);
  });

  // fechar
  document.querySelectorAll('#artistaModal .close-modal').forEach(btn => btn.addEventListener('click', closeModalArtista));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.querySelector('#artistaModal')?.style.display === 'flex') closeModalArtista();
  });
}

export default { init, fetchArtistaDetails, closeModalArtista };
