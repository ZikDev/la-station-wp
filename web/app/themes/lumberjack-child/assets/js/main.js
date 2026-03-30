const radios = document.querySelectorAll('input[name="f_category"]');
const panels = document.querySelectorAll('.sub_domain');
const cards = document.querySelectorAll('.radio-card');

radios.forEach(radio => {
  radio.addEventListener('change', () => {
    panels.forEach(panel => {
      panel.classList.remove('active');
      panel.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
      panel.querySelectorAll('.cb-dropdown').forEach(dd => {
        dd.classList.remove('open');
        resetTriggerLabel(dd);
      });
    });
    cards.forEach(c => c.classList.remove('selected'));

    document.getElementById('sub_domain_' + radio.value).classList.add('active');
    radio.closest('.radio-card').classList.add('selected');
  });
});

document.querySelectorAll('.cb-dropdown').forEach(dropdown => {
  const trigger = dropdown.querySelector('.cb-dropdown-trigger');
  const closeBtn = dropdown.querySelector('.cb-close');
  const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');

  trigger.addEventListener('click', () => dropdown.classList.toggle('open'));
  closeBtn.addEventListener('click', () => dropdown.classList.remove('open'));
  checkboxes.forEach(cb => cb.addEventListener('change', () => updateTriggerLabel(dropdown)));
});

document.addEventListener('click', e => {
  document.querySelectorAll('.cb-dropdown.open').forEach(dd => {
    if (!dd.contains(e.target)) dd.classList.remove('open');
  });
});

function updateTriggerLabel(dropdown) {
  const label = dropdown.querySelector('.cb-dropdown-label');
  const trigger = dropdown.querySelector('.cb-dropdown-trigger');
  const title = dropdown.dataset.title;
  const checked = [...dropdown.querySelectorAll('input[type="checkbox"]:checked')];
  const MAX_CHARS = 28;

  if (checked.length === 0) {
    label.textContent = title;
    trigger.classList.remove('has-selection');
    return;
  }

  trigger.classList.add('has-selection');

  let text = '';
  for (const cb of checked) {
    const cbLabel = cb.closest('label')?.querySelector('.cb-text')?.textContent?.trim() ?? cb.value;
    const next = text ? text + ', ' + cbLabel : cbLabel;
    if (next.length > MAX_CHARS) { text += '...'; break; }
    text = next;
  }

  label.textContent = text;
}

function resetTriggerLabel(dropdown) {
  const label = dropdown.querySelector('.cb-dropdown-label');
  const trigger = dropdown.querySelector('.cb-dropdown-trigger');
  label.textContent = dropdown.dataset.title;
  trigger.classList.remove('has-selection');
}

document.querySelectorAll('.cb-dropdown').forEach(dropdown => {
  updateTriggerLabel(dropdown);
});