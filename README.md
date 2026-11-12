# Kino

Каталог фильмов и сериалов. Данные — Kinopoisk Unofficial API через Cloudflare Worker с кэшем.

## Почему Worker

Лимит API ~500 запросов/сутки. Без кэша этого не хватает.

Worker:
- хранит `KP_API_KEY` в Secrets (не в браузере)
- кэширует популярное/топ на 6 часов, карточки фильмов на 24 часа
- все посетители бьют в кэш, а не в API

## Деплой API (Worker)

```bash
npm i -g wrangler
npx wrangler login
npx wrangler deploy
npx wrangler secret put KP_API_KEY
# вставь ключ с https://kinopoiskapiunofficial.tech
```

Проверка: `https://твой-worker.workers.dev/api/health` → `{"ok":true,"hasKey":true}`

### Связка со статикой

**Вариант A — Cloudflare Pages + Worker route**
1. Pages: подключи этот репозиторий, output `/`
2. Worker: route `твой-домен/api/*` → этот worker

**Вариант B — один Worker**  
Отдавай статику через Workers Assets / Pages, API на `/api/*`.

Фронт сам ходит на `/api/...`. Если прокси нет — fallback на ключ в «Ключ» (localStorage).

## Плеер

На `film.html` блок `#player` — вставь iframe или свой скрипт.

## LiveInternet

Официальный код в `js/liveinternet.js`, бейдж в футере.
