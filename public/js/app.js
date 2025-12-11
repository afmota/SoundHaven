// /public/js/app.js
import modalAlbum from './modules/modalAlbum.js';
import modalArtista from './modules/modalArtista.js';

// Inicialização da aplicação UI
document.addEventListener('DOMContentLoaded', () => {
  modalAlbum.init();
  modalArtista.init();
  // futuro: inicializar outros módulos (busca, filtros, etc.)
  console.info('App initialized (ES6 modules)');
});
