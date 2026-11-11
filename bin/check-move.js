'use strict';
/* Что ответит новый сайт на каждый адрес старого.
 *
 *   node bin/check-move.js https://адрес-нового-сайта
 *
 * Гонять перед каждым переключением и после него. Проверка идёт до
 * конца цепочки: правило, ведущее на 404, не лучше отсутствующего, а
 * цепочка из двух переадресаций теряет часть веса ссылки. Поэтому
 * смотрим и код, и куда в итоге приводит, и за сколько шагов.
 *
 * Мера — data/old-site-urls.txt, снятый обходом действующего сайта. */

const NEW = process.argv[2] || 'https://sklad-web-production.up.railway.app';
const fs = require('fs');
const path = require('path');

const urls = fs.readFileSync(path.join(__dirname, '../data/old-site-urls.txt'), 'utf8')
  .split('\n')
  .filter(l => l.trim() !== '' && !l.startsWith('#'))
  .map(l => l.split('\t')[0].replace(/%2F/gi, '/'));

async function follow(path, max = 5) {
  const chain = [];
  let current = path;
  for (let i = 0; i < max; i++) {
    const res = await fetch(NEW + current, {
      redirect: 'manual',
      headers: { 'User-Agent': 'Mozilla/5.0 (compatible; site-migration-check)' },
    });
    chain.push(res.status);
    if (res.status < 300 || res.status >= 400) {
      return { final: res.status, hops: chain.length - 1, to: current, chain };
    }
    const loc = res.headers.get('location') || '';
    current = loc.replace(/^https?:\/\/[^/]+/, '') || '/';
  }
  return { final: 0, hops: max, to: current, chain };
}

(async () => {
  const rows = [];
  for (const u of urls) {
    try {
      rows.push([u, await follow(u)]);
    } catch (e) {
      rows.push([u, { final: -1, hops: 0, to: '', chain: [e.message] }]);
    }
    if (rows.length % 40 === 0) process.stderr.write(`проверено ${rows.length}\n`);
  }

  const ok = rows.filter(([, r]) => r.final === 200);
  const bad = rows.filter(([, r]) => r.final !== 200);
  const long = ok.filter(([, r]) => r.hops > 1);

  console.log(`Адресов проверено:            ${rows.length}`);
  console.log(`Приводят на живую страницу:   ${ok.length}`);
  console.log(`Не приводят:                  ${bad.length}`);
  console.log(`Цепочка длиннее одного шага:  ${long.length}`);

  if (bad.length) {
    console.log('\n--- не приводят ---');
    bad.forEach(([u, r]) => console.log(`  ${r.final}  ${u}\n       цепочка: ${r.chain.join(' → ')}`));
  }
  if (long.length) {
    console.log('\n--- лишние шаги ---');
    long.slice(0, 20).forEach(([u, r]) => console.log(`  ${u} → ${r.to}  (${r.chain.join(' → ')})`));
  }

  // Куда сходятся адреса: если десяток старых страниц уходит на одну
  // новую, это осознанное слияние — но видеть его надо.
  const targets = {};
  ok.forEach(([, r]) => { targets[r.to] = (targets[r.to] || 0) + 1; });
  const merged = Object.entries(targets).filter(([, n]) => n > 2).sort((a, b) => b[1] - a[1]);
  if (merged.length) {
    console.log('\n--- на один адрес сходятся три и более старых ---');
    merged.slice(0, 15).forEach(([t, n]) => console.log(`  ${n}  →  ${t}`));
  }
})();
