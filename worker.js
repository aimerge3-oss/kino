/**
 * Cloudflare Worker: static + Kinopoisk API proxy
 * Keys: KP_API_KEY1 .. KP_API_KEY6
 * Static assets get long Cache-Control to cut free-tier asset requests
 */

const KP_BASE = 'https://kinopoiskapiunofficial.tech/api/v2.2';
const DAILY_SOFT_LIMIT = 480;

const TTL = {
  collections: 6 * 3600,
  film: 24 * 3600,
  search: 30 * 60,
  videos: 24 * 3600,
  similars: 12 * 3600,
  default: 3600
};

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    if (url.pathname.startsWith('/api/')) {
      return handleApi(request, env, ctx, url);
    }

    if (env.ASSETS) {
      return serveAsset(request, env, url);
    }

    return new Response('ASSETS binding missing',
      { status: 500, headers: { 'content-type': 'text/plain; charset=utf-8' } });
  }
};

async function serveAsset(request, env, url) {
  const res = await env.ASSETS.fetch(request);
  const path = url.pathname;
  const headers = new Headers(res.headers);

  // Long browser+edge cache for static — fewer repeat Asset requests
  if (path.endsWith('.css') || path.endsWith('.js')) {
    headers.set('Cache-Control', 'public, max-age=604800, stale-while-revalidate=86400');
  } else if (path.endsWith('.html') || path === '/' || path.endsWith('/')) {
    headers.set('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
  } else {
    headers.set('Cache-Control', 'public, max-age=86400');
  }

  return new Response(res.body, {
    status: res.status,
    statusText: res.statusText,
    headers
  });
}

function listKeys(env) {
  const keys = [];
  for (let i = 1; i <= 12; i++) {
    const v = env['KP_API_KEY' + i];
    if (v && String(v).trim()) keys.push(String(v).trim());
  }
  if (env.KP_API_KEY && String(env.KP_API_KEY).trim()) {
    keys.push(String(env.KP_API_KEY).trim());
  }
  if (env.KP_API_KEYS) {
    String(env.KP_API_KEYS)
      .split(/[,\n;]+/)
      .map((k) => k.trim())
      .filter(Boolean)
      .forEach((k) => keys.push(k));
  }
  return [...new Set(keys)];
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

async function getUsage(env, day, index) {
  if (!env.USAGE) return 0;
  const v = await env.USAGE.get('u:' + day + ':' + index);
  return parseInt(v || '0', 10) || 0;
}

async function incrUsage(env, day, index) {
  if (!env.USAGE) return;
  const key = 'u:' + day + ':' + index;
  const n = (await getUsage(env, day, index)) + 1;
  await env.USAGE.put(key, String(n), { expirationTtl: 172800 });
}

async function pickKey(env) {
  const keys = listKeys(env);
  if (!keys.length) return null;

  const day = today();
  let bestIdx = 0;
  let bestCount = Infinity;

  for (let i = 0; i < keys.length; i++) {
    const count = await getUsage(env, day, i);
    if (count < bestCount) {
      bestCount = count;
      bestIdx = i;
    }
  }

  let under = -1;
  let underCount = Infinity;
  for (let i = 0; i < keys.length; i++) {
    const count = await getUsage(env, day, i);
    if (count < DAILY_SOFT_LIMIT && count < underCount) {
      underCount = count;
      under = i;
    }
  }
  if (under >= 0) {
    bestIdx = under;
    bestCount = underCount;
  }

  return {
    key: keys[bestIdx],
    index: bestIdx,
    day,
    usage: bestCount,
    totalKeys: keys.length
  };
}

async function usageSummary(env) {
  const keys = listKeys(env);
  const day = today();
  const rows = [];
  for (let i = 0; i < keys.length; i++) {
    const count = await getUsage(env, day, i);
    rows.push({
      index: i,
      used: count,
      softLimit: DAILY_SOFT_LIMIT,
      remaining: Math.max(0, DAILY_SOFT_LIMIT - count)
    });
  }
  return { day, keyCount: keys.length, usage: rows };
}

async function handleApi(request, env, ctx, url) {
  if (request.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders() });
  }

  if (url.pathname === '/api/health') {
    const keys = listKeys(env);
    const summary = await usageSummary(env);
    return json({
      ok: true,
      hasKey: keys.length > 0,
      keyCount: keys.length,
      sources: {
        KEY1: Boolean(env.KP_API_KEY1),
        KEY2: Boolean(env.KP_API_KEY2),
        KEY3: Boolean(env.KP_API_KEY3),
        KEY4: Boolean(env.KP_API_KEY4),
        KEY5: Boolean(env.KP_API_KEY5),
        KEY6: Boolean(env.KP_API_KEY6),
        KEY: Boolean(env.KP_API_KEY),
        KEYS_CSV: Boolean(env.KP_API_KEYS)
      },
      hasUsageKV: Boolean(env.USAGE),
      ...summary
    });
  }

  if (url.pathname === '/api/usage') {
    return json(await usageSummary(env));
  }

  const picked = await pickKey(env);
  if (!picked) {
    return json({ error: 'No API keys. Add KP_API_KEY1… in Worker Settings' }, 503);
  }

  const kpPath = url.pathname.replace(/^\/api/, '') + url.search;

  if (!kpPath.startsWith('/films')) {
    return json({ error: 'forbidden' }, 403);
  }

  const cacheKey = new Request('https://kp-cache.internal' + kpPath, { method: 'GET' });
  const cache = caches.default;

  const cached = await cache.match(cacheKey);
  if (cached) {
    const hit = new Response(cached.body, cached);
    hit.headers.set('X-Cache', 'HIT');
    hit.headers.set('Access-Control-Allow-Origin', '*');
    return hit;
  }

  async function callUpstream(apiKey, keyIndex) {
    const upstream = await fetch(KP_BASE + kpPath, {
      headers: {
        'X-API-KEY': apiKey,
        'Content-Type': 'application/json',
        Accept: 'application/json'
      }
    });
    ctx.waitUntil(incrUsage(env, picked.day, keyIndex));
    return upstream;
  }

  let upstream = await callUpstream(picked.key, picked.index);

  if (upstream.status === 402 || upstream.status === 429) {
    if (env.USAGE) {
      await env.USAGE.put(
        'u:' + picked.day + ':' + picked.index,
        String(DAILY_SOFT_LIMIT + 50),
        { expirationTtl: 172800 }
      );
    }
    const retry = await pickKey(env);
    if (retry && retry.index !== picked.index) {
      upstream = await callUpstream(retry.key, retry.index);
      picked.index = retry.index;
    }
  }

  const body = await upstream.text();
  const ttl = pickTtl(kpPath);

  const response = new Response(body, {
    status: upstream.status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'public, max-age=' + ttl,
      'X-Cache': 'MISS',
      'X-Key-Index': String(picked.index),
      ...corsHeaders()
    }
  });

  if (upstream.ok) {
    ctx.waitUntil(cache.put(cacheKey, response.clone()));
  }

  return response;
}

function pickTtl(path) {
  if (path.includes('/collections')) return TTL.collections;
  if (path.includes('/videos')) return TTL.videos;
  if (path.includes('/similars')) return TTL.similars;
  if (path.includes('keyword=')) return TTL.search;
  if (/\/films\/\d+/.test(path)) return TTL.film;
  return TTL.default;
}

function corsHeaders() {
  return {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type'
  };
}

function json(obj, status = 200) {
  return new Response(JSON.stringify(obj), {
    status,
    headers: { 'Content-Type': 'application/json', ...corsHeaders() }
  });
}
