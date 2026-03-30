const radios = document.querySelectorAll('input[name="f_category"]');
const panels = document.querySelectorAll('.sub_domain');
const cards = document.querySelectorAll('.radio-card');

radios.forEach(radio => {
  radio.addEventListener('change', () => {
    // Réinitialiser tous les panneaux et cartes
    panels.forEach(panel => {
      panel.classList.remove('active');
      // Reset des checkboxes du panneau caché
      panel.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    });

    cards.forEach(card => card.classList.remove('selected'));

    // Activer le panneau correspondant
    document.getElementById('sub_domain_' + radio.value).classList.add('active');

    // Marquer la carte radio comme sélectionnée
    radio.closest('.radio-card').classList.add('selected');
  });
});