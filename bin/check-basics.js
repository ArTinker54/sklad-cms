'use strict';
/* Технические основы страницы — то, что обычно проверяют в аудитах.
 *
 *   node bin/check-basics.js https://адрес-сайта /путь /ещё-путь
 *
 * Считает по фактам, а не по впечатлению: пункт «нет микроразметки»
 * в присланном аудите оказался неверным наполовину, и спорить об этом
 * лучше цифрами.
 */

const BASE = (process.argv[2] || '').replace(/\/$/, '');
const PAGES = process.argv.slice(3);
if (!BASE) {
  console.error('Укажите адрес сайта');
  process.exit(1);
}

const get = async (url) => {
  const res = await fetch(url, {
    redirect: 'manual',
    headers: { 'User-Agent': 'Mozilla/5.0 (compatible; basics-check)' },
  });
  const body = res.status < 300 || res.status >= 400 ? await res.text() : '';
  return { status: res.status, location: res.headers.get('location') || '', body };
};

const one = (html, re) => ((html.match(re) || [])[1] || '').trim();

async function page(path) {
  const { status, body } = await get(BASE + path);
  const h1 = [...body.matchAll(/<h1\b[^>]*>([\s\S]*?)<\/h1>/gi)]
    .map(m => m[1].replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim())
    .filter(Boolean);

  return {
    path,
    status,
    lang: one(body, /<html[^>]*\blang="([^"]*)"/i) || '—',
    title: one(body, /<title>([^<]*)<\/title>/i) || '—',
    description: one(body, /<meta\s+name="description"\s+content="([^"]*)"/i) ? 'есть' : 'НЕТ',
    h1: h1.length === 1 ? 'один' : (h1.length === 0 ? 'НЕТ' : h1.length + ' штуки'),
    canonical: one(body, /<link[^>]*rel="canonical"[^>]*href="([^"]*)"/i) ? 'есть' : 'НЕТ',
    og: /<meta[^>]*property="og:/i.test(body) ? 'есть' : 'НЕТ',
    favicon: /<link[^>]*rel="(shortcut )?icon"/i.test(body) ? 'есть' : 'НЕТ',
    weight: Math.round(Buffer.byteLength(body) / 1024) + ' КБ',
  };
}

(async () => {
  console.log(BASE + '\n');

  for (const p of (PAGES.length ? PAGES : ['/'])) {
    const r = await page(p);
    console.log(`${r.path}  [${r.status}]  ${r.weight}`);
    console.log(`  lang ${r.lang} · заголовок «${r.title.slice(0, 60)}»`);
    console.log(`  описание ${r.description} · H1 ${r.h1} · canonical ${r.canonical}`
      + ` · Open Graph ${r.og} · значок ${r.favicon}`);
  }

  // Служебное, общее для всего сайта.
  console.log('\nобщее по сайту:');
  const robots = await get(BASE + '/robots.txt');
  console.log(`  robots.txt: ${robots.status}`
    + (robots.status === 200 ? (/sitemap/i.test(robots.body) ? ', карта указана' : ', КАРТА НЕ УКАЗАНА') : ''));

  const map = await get(BASE + '/sitemap.xml');
  const locs = (map.body.match(/<loc>/g) || []).length;
  console.log(`  sitemap.xml: ${map.status}`
    + (map.location ? ' → ' + map.location : '')
    + (map.status === 200 ? `, адресов ${locs}` + (locs === 0 ? ' — ПУСТАЯ' : '') : ''));

  // Несуществующий адрес обязан отвечать 404, а не 200: страница-призрак
  // с кодом 200 попадает в индекс и разбавляет сайт мусором.
  const ghost = await get(BASE + '/proverka-nesushchestvuyushchego-adresa-12345');
  console.log(`  несуществующий адрес: ${ghost.status}`
    + (ghost.status === 200 ? ' — ДОЛЖЕН БЫТЬ 404' : ''));
})();
