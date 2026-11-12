(function () {
  'use strict';

  const BASE = 'https://www.kinopoisk.ws';

  const form = document.getElementById('searchForm');
  const input = document.getElementById('searchInput');
  const modeButtons = document.querySelectorAll('.btn-mode');
  const hint = document.getElementById('modeHint');

  let currentPath = '/';
  let currentName = 'id';

  const hints = {
    id: 'Введи ID фильма с Кинопоиска и нажми «Искать».',
    name: 'Введи название фильма или сериала.',
    torrent: 'Поиск торрентов по названию фильма или сериала.'
  };

  function setMode(btn) {
    modeButtons.forEach((b) => b.classList.remove('is-active'));
    btn.classList.add('is-active');

    const placeholder = btn.dataset.placeholder;
    const type = btn.dataset.type;
    const name = btn.dataset.name;
    const path = btn.dataset.path;
    const mode = btn.dataset.mode;

    input.placeholder = placeholder;
    input.type = type;
    input.name = name;
    input.value = '';
    input.focus();

    currentPath = path;
    currentName = name;

    if (hint) {
      hint.textContent = hints[mode] || '';
    }
  }

  modeButtons.forEach((btn) => {
    btn.addEventListener('click', () => setMode(btn));
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const value = (input.value || '').trim();
    if (!value) {
      input.focus();
      return;
    }

    let url;

    if (currentPath === '/') {
      // ID mode → /film/{id}/
      const id = value.replace(/\D/g, '');
      if (!id) {
        input.focus();
        return;
      }
      url = BASE + '/film/' + id + '/';
    } else {
      // name / torrent → path?name=...
      const params = new URLSearchParams();
      params.set(currentName, value);
      url = BASE + currentPath + '?' + params.toString();
    }

    window.open(url, '_blank', 'noopener,noreferrer');
  });

  // Keyboard: Enter already handled by form; Esc clears
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      input.value = '';
    }
  });
})();
