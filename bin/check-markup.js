'use strict';
/* Микроразметка и подписи к картинкам — сколько их на самом деле.
 *
 *   node bin/check-markup.js https://адрес-сайта
 *
 * Считает по нескольким типовым страницам: главная, раздел, карточка
 * товара, контакты. Проверка нужна потому, что и то и другое легко
 * потерять при правке шаблона, а заметит это не разработчик, а падение
 * переходов из поиска по картинкам через пару месяцев.
 */

const BASE = (process.argv[2] || 'https://sklad-web-production.up.railway.app').replace(/\/$/, '');
const PAGES = process.argv.slice(3);

const TYPES = ['Product', 'Offer', 'BreadcrumbList', 'ItemList', 'Organization', 'PostalAddress', 'Brand'];

async function look(path) {
  const res = await fetch(BASE + path, {
    headers: { 'User-Agent': 'Mozilla/5.0 (compatible; markup-check)' },
  });
  const html = await res.text();

  // Микроразметка бывает двух видов: в атрибутах разметки и отдельным
  // блоком JSON-LD. Считаем оба — поисковику годится любой.
  const found = new Set();
  for (const t of TYPES) {
    if (new RegExp(`itemtype="https?://schema\\.org/${t}"`).test(html)) found.add(t + ' (в разметке)');
    if (new RegExp(`"@type"\\s*:\\s*"${t}"`).test(html)) found.add(t + ' (JSON-LD)');
  }

  const imgs = [...html.matchAll(/<img\b[^>]*>/gi)].map(m => m[0]);
  const withAlt = imgs.filter(i => /\balt\s*=\s*"[^"]+"/i.test(i));
  const emptyAlt = imgs.filter(i => /\balt\s*=\s*""/i.test(i));
  const noAlt = imgs.filter(i => !/\balt\s*=/i.test(i));

  return {
    path, status: res.status, types: [...found],
    imgs: imgs.length, withAlt: withAlt.length, emptyAlt: emptyAlt.length, noAlt: noAlt.length,
    samples: noAlt.slice(0, 2).map(s => s.slice(0, 90)),
  };
}

(async () => {
  const pages = PAGES.length ? PAGES : ['/'];
  console.log(BASE + '\n');

  let imgs = 0, ok = 0, empty = 0, bad = 0;
  for (const p of pages) {
    const r = await look(p);
    imgs += r.imgs; ok += r.withAlt; empty += r.emptyAlt; bad += r.noAlt;
    console.log(`${r.path}  [${r.status}]`);
    console.log(`  микроразметка: ${r.types.length ? r.types.join(', ') : 'НЕТ'}`);
    console.log(`  картинок ${r.imgs}: с подписью ${r.withAlt}, `
      + `пустая подпись ${r.emptyAlt} (декор), без атрибута ${r.noAlt}`);
    r.samples.forEach(s => console.log('    без alt: ' + s));
    console.log('');
  }

  console.log(`Итого картинок ${imgs}: с подписью ${ok}, декоративных ${empty}, без атрибута ${bad}`);
})();
