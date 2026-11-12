# Kino (kinopoisk.ai)

Каталог фильмов и сериалов на данных Кинопоиска.

## Возможности

- Главная: поиск, популярное, топ рейтинга
- Страница фильма: постер, описание, рейтинг, трейлер, **слот под плеер**
- Поиск по названию и по ID
- Похожие фильмы

## Плеер

На странице `film.html` есть блок `#player`.

Ты можешь вставить свой плеер так:

```html
<div class="player" id="player" data-kinopoisk-id="326">
  <iframe src="ТВОЙ_URL" allowfullscreen allow="autoplay"></iframe>
</div>
```

Или через JS после загрузки фильма:

```js
window.initPlayer = function ({ id, title, playerEl }) {
  // твой код плеера
  playerEl.innerHTML = `<iframe src="..." ...></iframe>`;
};
```

В `film.js` уже выставляются `data-kinopoisk-id` и `data-title` на `#player`.

## API-ключ

1. Зарегистрируйся на [kinopoiskapiunofficial.tech](https://kinopoiskapiunofficial.tech)
2. На сайте нажми **Ключ** в шапке и вставь ключ

Ключ хранится в `localStorage` браузера.

## Структура

```
index.html      — главная
film.html       — страница фильма (?id=326)
css/style.css
js/
  api.js        — запросы к API
  ui.js         — карточки, модалка ключа
  home.js       — главная
  film.js       — страница фильма
```

## Деплой

Статический сайт. Можно GitHub Pages, Cloudflare Pages, Netlify или свой сервер на `kinopoisk.ai`.

```bash
npx serve .
```
