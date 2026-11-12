(function () {
  'use strict';

  const BASE = 'https://www.kinopoisk.ws';
  // Неофициальный API Кинопоиска. Получи бесплатный ключ на https://kinopoiskapiunofficial.tech
  // и вставь его сюда (или сохрани в localStorage через консоль: localStorage.setItem('kp_api_key', 'ТВОЙ_КЛЮЧ'))
  const API_KEY = localStorage.getItem('kp_api_key') || '';
  const API_URL = 'https://kinopoiskapiunofficial.tech/api/v2.2/films/';

  const form = document.getElementById('searchForm');
  const input = document.getElementById('searchInput');
  const submitBtn = document.getElementById('submitBtn');
  const modeButtons = document.querySelectorAll('.btn-mode');
  const hint = document.getElementById('modeHint');

  const resultEl = document.getElementById('result');
  const resultPoster = document.getElementById('resultPoster');
  const resultPosterPlaceholder = document.getElementById('resultPosterPlaceholder');
  const resultTitle = document.getElementById('resultTitle');
  const resultOriginal = document.getElementById('resultOriginal');
  const resultYear = document.getElementById('resultYear');
  const resultRating = document.getElementById('resultRating');
  const resultGenres = document.getElementById('resultGenres');
  const resultOpen = document.getElementById('resultOpen');
  const resultError = document.getElementById('resultError');

  let currentPath = '/';
  let currentName = 'id';
  let currentMode = 'id';

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
    currentMode = mode;

    if (hint) {
      hint.textContent = hints[mode] || '';
    }

    // Скрываем карточку при смене режима
    hideResult();
  }

  function hideResult() {
    resultEl.hidden = true;
    resultError.hidden = true;
    resultError.textContent = '';
  }

  function showError(msg) {
    resultEl.hidden = false;
    resultError.hidden = false;
    resultError.textContent = msg;
    // Прячем саму карточку, оставляем только ошибку
    resultEl.querySelector('.result__card').style.display = 'none';
  }

  function showCard() {
    resultEl.hidden = false;
    resultError.hidden = true;
    resultEl.querySelector('.result__card').style.display = 'flex';
  }

  async function fetchFilmById(id) {
    if (!API_KEY) {
      showError(
        'Нужен API-ключ. Зарегистрируйся на kinopoiskapiunofficial.tech, получи ключ и выполни в консоли:\n' +
        'localStorage.setItem("kp_api_key", "ТВОЙ_КЛЮЧ")\nзатем обнови страницу.'
      );
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Загрузка…';
    hideResult();

    try {
      const res = await fetch(API_URL + id, {
        headers: {
          'X-API-KEY': API_KEY,
          'Content-Type': 'application/json'
        }
      });

      if (res.status === 401 || res.status === 403) {
        showError('Неверный или просроченный API-ключ. Проверь ключ на kinopoiskapiunofficial.tech');
        return;
      }

      if (res.status === 404) {
        showError('Фильм с таким ID не найден.');
        return;
      }

      if (!res.ok) {
        showError('Ошибка API: ' + res.status + '. Попробуй позже.');
        return;
      }

      const data = await res.json();

      // Название
      const title = data.nameRu || data.nameOriginal || data.nameEn || 'Без названия';
      resultTitle.textContent = title;

      // Оригинальное название
      if (data.nameOriginal && data.nameOriginal !== title) {
        resultOriginal.textContent = data.nameOriginal;
        resultOriginal.hidden = false;
      } else if (data.nameEn && data.nameEn !== title) {
        resultOriginal.textContent = data.nameEn;
        resultOriginal.hidden = false;
      } else {
        resultOriginal.textContent = '';
        resultOriginal.hidden = true;
      }

      // Год
      resultYear.textContent = data.year ? String(data.year) : '';

      // Рейтинг
      if (data.ratingKinopoisk) {
        resultRating.textContent = '★ ' + data.ratingKinopoisk;
        resultRating.hidden = false;
      } else if (data.ratingImdb) {
        resultRating.textContent = 'IMDb ' + data.ratingImdb;
        resultRating.hidden = false;
      } else {
        resultRating.hidden = true;
      }

      // Жанры
      if (data.genres && data.genres.length) {
        resultGenres.textContent = data.genres.map((g) => g.genre).join(', ');
      } else {
        resultGenres.textContent = '';
      }

      // Постер
      const posterUrl = data.posterUrlPreview || data.posterUrl;
      if (posterUrl) {
        resultPoster.src = posterUrl;
        resultPoster.alt = title;
        resultPoster.hidden = false;
        resultPosterPlaceholder.hidden = true;
        resultPoster.onerror = function () {
          resultPoster.hidden = true;
          resultPosterPlaceholder.hidden = false;
        };
      } else {
        resultPoster.hidden = true;
        resultPosterPlaceholder.hidden = false;
      }

      // Ссылка
      resultOpen.href = BASE + '/film/' + id + '/';

      showCard();
    } catch (err) {
      console.error(err);
      showError('Не удалось загрузить данные. Проверь интернет или API-ключ.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Искать';
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

    if (currentMode === 'id') {
      const id = value.replace(/\D/g, '');
      if (!id) {
        input.focus();
        return;
      }
      fetchFilmById(id);
      return;
    }

    // name / torrent → открываем в новой вкладке
    const params = new URLSearchParams();
    params.set(currentName, value);
    const url = BASE + currentPath + '?' + params.toString();
    window.open(url, '_blank', 'noopener,noreferrer');
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      input.value = '';
      hideResult();
    }
  });
})();
