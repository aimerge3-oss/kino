/** Shared UI: cards, modal, header search */
(function () {
  'use strict';

  // —— API key modal ——
  const modal = document.getElementById('keyModal');
  const keyInput = document.getElementById('keyInput');
  const btnKey = document.getElementById('btnKey');
  const keySave = document.getElementById('keySave');
  const keyCancel = document.getElementById('keyCancel');

  function openKeyModal() {
    if (!modal) return;
    keyInput.value = KP.getKey();
    modal.hidden = false;
    keyInput.focus();
  }

  function closeKeyModal() {
    if (modal) modal.hidden = true;
  }

  if (btnKey) btnKey.addEventListener('click', openKeyModal);
  if (keyCancel) keyCancel.addEventListener('click', closeKeyModal);
  if (keySave) {
    keySave.addEventListener('click', () => {
      KP.setKey(keyInput.value);
      closeKeyModal();
      location.reload();
    });
  }
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeKeyModal();
    });
  }

  // —— Film card HTML ——
  window.renderFilmCard = function (film) {
    const id = film.kinopoiskId || film.filmId || film.id;
    const title = film.nameRu || film.nameOriginal || film.nameEn || 'Без названия';
    const year = film.year || '';
    const rating = film.ratingKinopoisk || film.rating || '';
    const poster = film.posterUrlPreview || film.posterUrl || '';

    const ratingHtml = rating
      ? `<span class="card__rating">★ ${rating}</span>`
      : '';

    return `
      <a class="card" href="film.html?id=${id}">
        <div class="card__poster">
          ${poster
            ? `<img src="${poster}" alt="" loading="lazy">`
            : `<div class="card__no-poster">Нет постера</div>`}
          ${ratingHtml}
        </div>
        <div class="card__body">
          <div class="card__title">${escapeHtml(title)}</div>
          <div class="card__year">${year}</div>
        </div>
      </a>`;
  };

  function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  window.escapeHtml = escapeHtml;

  // —— Header search → home or film ——
  const headerForm = document.getElementById('headerSearch');
  const headerInput = document.getElementById('headerInput');
  if (headerForm && headerInput) {
    headerForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const q = headerInput.value.trim();
      if (!q) return;
      if (/^\d+$/.test(q)) {
        location.href = 'film.html?id=' + q;
      } else {
        location.href = './?q=' + encodeURIComponent(q);
      }
    });
  }

  // Expose open modal for other scripts
  window.openKeyModal = openKeyModal;
})();
