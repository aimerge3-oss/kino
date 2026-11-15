# Kino

Каталог фильмов. Данные — Kinopoisk Unofficial API через Cloudflare Worker.

## Несколько API-ключей (ротация)

Лимит ~500 запросов/сутки **на ключ**. Можно повесить 5–6 ключей — Worker каждый раз берёт **наименее использованный за сегодня**.

С кэшем (топ/популярное 6ч, фильм 24ч) реальные хиты в API сильно меньше числа просмотров страниц.

### 1. KV для счётчиков

```bash
npx wrangler kv namespace create kino-usage
```

Скопируй `id` в `wrangler.toml` → `[[kv_namespaces]]` → `id = "..."`

### 2. Секрет со всеми ключами

```bash
npx wrangler secret put KP_API_KEYS
```

Вставь одной строкой через запятую:

```
ключ1,ключ2,ключ3,ключ4,ключ5,ключ6
```

Старый одиночный `KP_API_KEY` тоже поддерживается.

### 3. Деплой

```bash
npx wrangler deploy
```

### Проверка

```
GET /api/health
```

Пример ответа:

```json
{
  "ok": true,
  "hasKey": true,
  "keyCount": 6,
  "day": "2026-11-15",
  "usage": [
    { "index": 0, "used": 12, "softLimit": 480, "remaining": 468 },
    { "index": 1, "used": 3, "softLimit": 480, "remaining": 477 }
  ]
}
```

`GET /api/usage` — только счётчики.

При 402/429 Worker помечает ключ как исчерпанный на сегодня и пробует другой.

## Плеер

На `film.html` блок `#player` — вставь iframe или скрипт.

## LiveInternet

Код в `js/liveinternet.js`.
