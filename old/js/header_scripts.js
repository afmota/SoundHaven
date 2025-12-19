// Arquivo: js/header_scripts.js

document.addEventListener('DOMContentLoaded', function() {
    const dropdownContainer = document.getElementById('profileDropdown');

    if (!dropdownContainer) return;

    const avatarTrigger = dropdownContainer.querySelector('.profile-avatar-trigger');

    // Função para alternar a visibilidade do menu
    avatarTrigger.addEventListener('click', function(event) {
        event.stopPropagation(); 
        dropdownContainer.classList.toggle('menu-aberto');
    });

    // Função para fechar o menu se o usuário clicar fora dele
    document.addEventListener('click', function(event) {
        if (!dropdownContainer.contains(event.target)) {
            dropdownContainer.classList.remove('menu-aberto');
        }
    });
});