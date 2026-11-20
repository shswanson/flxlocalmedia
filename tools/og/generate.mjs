#!/usr/bin/env node
/**
 * Make the share images (Open Graph / Twitter cards) for flxlocalmedia.com.
 *
 * One 1200 x 630 PNG per page, in the bold style: a huge heavy Poppins headline,
 * a Kaushan Script eyebrow (gold, tilted -3deg, sentence case), real imagery from
 * the theme (page photo, station logo in the station's colors, client hero photo),
 * one big proof number where the page prints one, and the small FLX logo in the
 * bottom-left corner.
 *
 *   node generate.mjs --manifest manifest.json [--out DIR] [--only key,key] [--list]
 *
 *   --manifest  JSON from export-manifest.php (see README.md). It supplies each
 *               page's key, URL and the station colors and logos.
 *   --copy      bold-copy.json (default: next to this file). The headline, proof
 *               number, imagery and layout for each key.
 *   --out       where the PNGs go (default: <theme>/assets/images/og)
 *   --only      comma list of image keys to make (default: every key in the copy file)
 *   --keep      keep the 2x renders next to the output (in DIR/_2x)
 *   --theme     read images from this theme folder instead of the one in the manifest
 *
 * Needs: node 18+, the "playwright" package, Google Chrome, python3 with Pillow.
 * Chrome path: CHROME_PATH or the macOS default. Fonts come from Google Fonts, so
 * the machine needs internet; the run stops if Poppins or Kaushan Script did not load.
 */
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { createRequire } from 'node:module';

const here = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);

/* ---------- arguments ---------- */
const args = process.argv.slice(2);
const opt = (name, fallback = '') => {
  const i = args.indexOf('--' + name);
  return i >= 0 && args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : fallback;
};
const flag = (name) => args.includes('--' + name);

const manifestPath = opt('manifest');
if (!manifestPath) {
  console.error('Usage: node generate.mjs --manifest manifest.json [--out DIR] [--only key,key] [--list]');
  process.exit(1);
}
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const copy = JSON.parse(fs.readFileSync(opt('copy', path.join(here, 'bold-copy.json')), 'utf8'));
const theme = opt('theme', manifest.theme);
const outDir = opt('out', path.join(theme, 'assets/images/og'));
const only = opt('only') ? new Set(opt('only').split(',')) : null;

const byKey = Object.fromEntries(manifest.rows.map((r) => [r.key, r]));
const keys = Object.keys(copy).filter((k) => !k.startsWith('_') && k !== 'stations' && (!only || only.has(k)));

if (flag('list')) {
  for (const k of keys) console.log(k.padEnd(40), copy[k].type);
  process.exit(0);
}

let chromium;
try {
  ({ chromium } = require('playwright'));
} catch {
  ({ chromium } = require(path.join(process.env.NODE_PATH || '', 'playwright')));
}

/* ---------- helpers ---------- */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
/** Escape, then turn *word* into the gold word and \n into a line break. */
const rich = (s) => esc(s).replace(/\*([^*]+)\*/g, '<em>$1</em>').replace(/\n/g, '<br>');

const MIME = { '.svg': 'image/svg+xml', '.png': 'image/png', '.webp': 'image/webp', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg' };
const dataUri = (file) => {
  if (!file || !fs.existsSync(file)) return '';
  const mime = MIME[path.extname(file).toLowerCase()] || 'application/octet-stream';
  return `data:${mime};base64,${fs.readFileSync(file).toString('base64')}`;
};
const themeFile = (rel) => path.join(theme, rel);
/** A station's logo file: the manifest has an absolute path made on the machine that exported it. */
const stationLogo = (key) => {
  const row = byKey[key];
  if (!row || !row.og.logo) return '';
  const m = /assets\/images\/.*$/.exec(row.og.logo);
  return m ? themeFile(m[0]) : row.og.logo;
};
const FLX_LOGO = () => dataUri(themeFile('assets/images/brand/FLX-Logo-Horizontal-White.png'));

const NAVY = '#003b5b';
const NAVY_DEEP = '#002238';
const GOLD = '#e3a72f';

/* ---------- the card ---------- */
function cardHtml(key, c) {
  const row = byKey[key] || {};
  const og = row.og || {};
  const type = c.type;
  const isStation = type === 'station';
  const bg = c.bg || (isStation ? og.bg : NAVY);
  const deep = c.deep || (isStation ? og.deep : NAVY_DEEP);
  const missing = [];
  const img = (rel) => {
    const f = themeFile(rel);
    if (!fs.existsSync(f)) missing.push(rel);
    return dataUri(f);
  };

  let imagery = '';
  let body = '';
  const proof = c.num ? `<div class="proof"><span class="n">${esc(c.num)}</span><span class="l">${esc(c.numLabel || '')}</span></div>` : '';
  const eyebrow = c.eyebrow ? `<div class="eyebrow">${esc(c.eyebrow)}</div>` : '';

  if (type === 'photo') {
    imagery = `<div class="photo"><img src="${img(c.img)}" alt="" style="object-position:${esc(c.pos || '14% 40%')}"></div>`;
  } else if (type === 'figure') {
    imagery = `<div class="figure"><img src="${img(c.img)}" alt="" style="object-position:${esc(c.pos || '0% 0%')}"></div>`;
  } else if (type === 'trio') {
    imagery = '<div class="trio">' + c.trio.map((s, i) => `<div class="pt pt${i}"><img src="${img(`assets/images/testimonials/${s}/hero-portrait.jpg`)}" alt=""></div>`).join('') + '</div>';
  } else if (type === 'tiles') {
    const list = c.tiles === 'stations' ? copy.stations : c.tiles;
    const n = list.length;
    const rows = n === 7 ? [2, 3, 2] : n === 3 ? [2, 1] : [n];
    let i = 0;
    imagery = `<div class="tiles tiles--${n >= 5 ? 'sm' : n === 1 ? 'one' : 'md'}">` + rows.map((r) => '<div class="trow">' + list.slice(i, (i += r)).map((k) => {
      const st = byKey[k] ? byKey[k].og : { bg: NAVY };
      return `<div class="tile" style="background:${st.bg}"><img src="${dataUri(stationLogo(k))}" alt=""></div>`;
    }).join('') + '</div>').join('') + '</div>';
  } else if (type === 'mark') {
    imagery = `<div class="mark"><img src="${dataUri(themeFile('assets/images/brand/FLX-Icon-White.svg'))}" alt=""></div>`;
  } else if (type === 'story') {
    imagery = `<div class="hero"><img src="${img(`assets/images/testimonials/${c.slug}/hero-wide.jpg`)}" alt="" style="object-position:${esc(c.pos || '30% 30%')}"></div><div class="shade"></div>
      <div class="play"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="58" fill="rgba(0,20,35,.55)" stroke="rgba(255,255,255,.85)" stroke-width="3"/><path d="M48 36l38 24-38 24z" fill="#fff"/></svg></div>`;
  }

  if (type === 'station') {
    const freqText = c.freq || (og.headline || '');
    const m = /^(\d+(?:\.\d)?)\s*(FM|AM)$/.exec(freqText);
    const big = m ? `<span class="num${m[1].length >= 5 ? ' num--5' : ''}">${esc(m[1])}</span><span class="band">${esc(m[2])}</span>` : `<span class="num num--word">${esc(freqText)}</span>`;
    const logo = c.logo ? themeFile(c.logo) : stationLogo(key);
    imagery = `<div class="slogo${c.num ? '' : ' slogo--tall'}"><img src="${dataUri(logo)}" alt=""></div>${c.num ? `<div class="sproof"><span class="n">${esc(c.num)}</span><span class="l">${esc(c.numLabel)}</span></div>` : ''}`;
    body = `<div class="col col--station">${eyebrow}<div class="freq">${big}</div><div class="tag">${esc(c.tag)}</div><div class="aud">${esc(c.aud)}</div></div>`;
  } else if (type === 'story') {
    body = `<div class="col col--story">${eyebrow}<div class="quote"><span class="qm">“</span><p class="q">${esc(c.quote)}</p></div><div class="who"><b>${esc(c.who)}</b><span>${esc(c.biz)}</span></div></div>`;
  } else {
    body = `<div class="col col--${type}">${eyebrow}<h1 class="hl">${rich(c.line)}</h1>${c.sub ? `<p class="sub">${rich(c.sub)}</p>` : ''}${proof}</div>`;
  }

  const html = `<!doctype html><html><head><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kaushan+Script&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:1200px;height:630px;overflow:hidden;background:${bg}}
body{font-family:'Poppins',sans-serif;color:#fff;-webkit-font-smoothing:antialiased}
.card{position:relative;width:1200px;height:630px;overflow:hidden;
  background:radial-gradient(900px 600px at 12% 8%,${bg} 0%,${bg} 40%,${deep} 140%)}
.eyebrow{font-family:'Kaushan Script',cursive;font-size:50px;line-height:1.1;color:${isStation ? '#fff' : GOLD};transform:rotate(-3deg);transform-origin:left center;white-space:nowrap;margin-bottom:6px;text-shadow:0 2px 12px rgba(0,0,0,.25)}
.hl,.sub,.proof,.q,.who{text-shadow:0 2px 14px rgba(0,20,35,.45)}
.col{position:absolute;left:56px;top:44px;height:486px;display:flex;flex-direction:column;overflow:hidden}
.col--photo,.col--figure,.col--mark{width:600px}
.col--trio{width:560px}
.col--tiles{width:580px}
.hl{font-weight:900;line-height:1.02;letter-spacing:-.025em;font-size:120px;margin-top:8px;overflow-wrap:normal}
.hl em{font-style:normal;color:${GOLD}}
.sub{margin-top:18px;font-size:44px;line-height:1.2;font-weight:700;color:rgba(255,255,255,.92)}
.proof{margin-top:auto;padding-top:14px;display:flex;flex-direction:column}
.proof .n{font-weight:900;font-size:88px;line-height:1;letter-spacing:-.03em;color:${GOLD}}
.proof .l{font-weight:700;font-size:36px;line-height:1.15;color:#fff;margin-top:4px}
.hl+.proof,.sub+.proof{margin-top:auto}
.logo{position:absolute;left:56px;bottom:46px;height:34px;z-index:5}

/* photo */
.photo{position:absolute;left:400px;top:0;width:800px;height:630px}
.photo img{width:100%;height:100%;object-fit:cover}
.photo::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,${bg} 0%,${bg} 24%,rgba(0,59,91,.0) 66%),linear-gradient(0deg,rgba(0,34,56,.35),rgba(0,34,56,0) 40%)}
.bar{position:absolute;left:0;right:0;bottom:0;height:14px;background:${GOLD};z-index:6}

/* figure */
.figure{position:absolute;left:690px;top:92px;width:470px;height:430px;border-radius:22px;overflow:hidden;border:8px solid #fff;box-shadow:0 22px 50px rgba(0,0,0,.45);transform:rotate(2.5deg);background:#0b2233}
.figure img{width:100%;height:100%;object-fit:cover}

/* trio of portraits */
.trio{position:absolute;inset:0}
.pt{position:absolute;width:210px;height:280px;border-radius:18px;overflow:hidden;border:7px solid #fff;box-shadow:0 18px 40px rgba(0,0,0,.45)}
.pt img{width:100%;height:100%;object-fit:cover;object-position:50% 30%}
.pt0{left:640px;top:52px;transform:rotate(-3deg)}
.pt1{left:800px;top:176px;transform:rotate(2deg);z-index:2}
.pt2{left:960px;top:300px;transform:rotate(-2deg);z-index:3}

/* station tiles */
.tiles{position:absolute;right:46px;top:0;bottom:0;display:flex;flex-direction:column;justify-content:center;gap:14px;align-items:center}
.trow{display:flex;gap:14px;justify-content:center}
.tile{display:flex;align-items:center;justify-content:center;border-radius:20px;box-shadow:0 12px 28px rgba(0,0,0,.35);border:3px solid rgba(255,255,255,.18)}
.tile img{max-width:82%;max-height:78%;object-fit:contain}
.tiles--sm .tile{width:162px;height:140px}
.tiles--md .tile{width:230px;height:200px}
.tiles--one .tile{width:340px;height:300px}
.tiles--sm{width:520px}.tiles--md{width:520px}.tiles--one{width:380px}

/* mark */
.mark{position:absolute;right:-90px;top:-40px;width:760px;height:710px;opacity:.16}
.mark img{width:100%;height:100%;object-fit:contain}

/* story */
.hero{position:absolute;inset:0}
.hero img{width:100%;height:100%;object-fit:cover}
.shade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,43,66,0) 0%,rgba(0,43,66,0) 26%,rgba(0,43,66,.9) 54%,rgba(0,43,66,.97) 100%),linear-gradient(0deg,rgba(0,24,38,.75),rgba(0,24,38,0) 30%)}
.play{position:absolute;left:150px;top:340px;width:124px;height:124px;filter:drop-shadow(0 6px 16px rgba(0,0,0,.4))}
.col--story{left:610px;top:46px;width:540px;height:540px}
.quote{margin-top:6px}
.qm{display:block;font-weight:900;font-size:130px;line-height:.7;height:62px;color:${GOLD}}
.q{font-weight:800;font-size:56px;line-height:1.1;letter-spacing:-.015em;margin-top:6px}
.who{margin-top:auto;display:flex;flex-direction:column;gap:2px;padding-bottom:6px}
.who b{font-size:42px;font-weight:800;color:${GOLD};line-height:1.15}
.who span{font-size:34px;font-weight:600;color:#fff;line-height:1.2}

/* station */
.col--station{width:640px;height:530px}
.freq{display:flex;align-items:baseline;gap:12px;line-height:.86;margin-top:-6px}
.num{font-weight:900;font-size:236px;letter-spacing:-.045em}
.num--5{font-size:196px}
.num--word{font-size:150px;letter-spacing:-.04em}
.band{font-weight:900;font-size:86px;letter-spacing:-.01em;color:${og.accent || GOLD}}
.tag{font-weight:800;font-size:56px;line-height:1.05;margin-top:16px;letter-spacing:-.01em}
.aud{display:inline-block;align-self:flex-start;margin-top:18px;background:rgba(255,255,255,.2);border-radius:14px;padding:8px 20px;font-weight:700;font-size:38px}
.slogo{position:absolute;right:60px;top:56px;width:420px;height:300px;display:flex;align-items:center;justify-content:center}
.slogo--tall{top:90px;height:420px;width:440px;right:56px}
.slogo img{max-width:100%;max-height:100%;object-fit:contain;filter:drop-shadow(0 8px 22px rgba(0,0,0,.3))}
.sproof{position:absolute;right:56px;top:392px;width:440px;display:flex;flex-direction:column;align-items:flex-start}
.sproof .n{font-weight:900;font-size:92px;line-height:1;letter-spacing:-.03em;color:#fff}
.sproof .l{font-weight:700;font-size:36px;color:rgba(255,255,255,.92)}
</style></head>
<body><div class="card">
  ${imagery}${body}
  ${isStation ? '' : '<div class="bar"></div>'}
  <img class="logo" src="${FLX_LOGO()}" alt="FLX Local Media">
</div></body></html>`;
  return { html, missing };
}

/** Shrink the headline (or quote) until the text column fits its box. */
const FIT = `
  (() => {
    const col = document.querySelector('.col');
    const el = document.querySelector('.hl, .q');
    if (!col || !el) return;
    const min = el.classList.contains('q') ? 34 : 60;
    let size = parseFloat(getComputedStyle(el).fontSize);
    const tooBig = () => col.lastElementChild.getBoundingClientRect().bottom > col.getBoundingClientRect().bottom + 1 || el.scrollWidth > el.clientWidth + 1;
    let guard = 120;
    while (tooBig() && size > min && guard-- > 0) { size -= 2; el.style.fontSize = size + 'px'; }
    return size + ' sh' + col.scrollHeight + '/' + col.clientHeight + ' sw' + el.scrollWidth + '/' + el.clientWidth;
  })();
`;

/* ---------- run ---------- */
fs.mkdirSync(outDir, { recursive: true });
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'flxlm-og-'));
const keep2x = flag('keep') ? path.join(outDir, '_2x') : '';
if (keep2x) fs.mkdirSync(keep2x, { recursive: true });

const browser = await chromium.launch({
  executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  args: ['--headless=new'],
});
const context = await browser.newContext({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 2 });
const page = await context.newPage();

const report = [];
for (const key of keys) {
  const { html: markup, missing } = cardHtml(key, copy[key]);
  if (missing.length) console.warn(`  ! ${key}: file not found: ${missing.join(', ')}`);
  const html = path.join(tmp, key + '.html');
  fs.writeFileSync(html, markup);
  await page.goto(pathToFileURL(html).href, { waitUntil: 'networkidle' });
  await page.evaluate(async () => {
    await Promise.all([document.fonts.load("900 60px 'Poppins'"), document.fonts.load("50px 'Kaushan Script'")]);
    await document.fonts.ready;
  });
  const fonts = await page.evaluate(() => ({
    poppins: [...document.fonts].some((f) => /Poppins/.test(f.family) && f.status === 'loaded'),
    kaushan: [...document.fonts].some((f) => /Kaushan/.test(f.family) && f.status === 'loaded'),
  }));
  if (!fonts.poppins || !fonts.kaushan) {
    console.error(`Fonts did not load (Poppins ${fonts.poppins}, Kaushan Script ${fonts.kaushan}). No internet, or Google Fonts is blocked. Stopping so no image is made with a stand-in font.`);
    await browser.close();
    process.exit(2);
  }
  const broken = await page.evaluate(() => [...document.images].filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.src.slice(0, 60)));
  if (broken.length) console.warn(`  ! ${key}: image did not load: ${broken.join(', ')}`);
  const size = await page.evaluate(FIT);
  const raw = path.join(keep2x || tmp, key + '.2x.png');
  await page.screenshot({ path: raw, type: 'png', clip: { x: 0, y: 0, width: 1200, height: 630 } });
  const dest = path.join(outDir, key + '.png');
  const out = execFileSync('python3', [path.join(here, 'postprocess.py'), raw, dest], { encoding: 'utf8' }).trim();
  const kb = Math.round(fs.statSync(dest).size / 1024);
  report.push({ key, kb });
  console.log(`${key.padEnd(40)} ${String(kb).padStart(4)} KB  text ${size ?? '-'}  (${out.split(' ')[1]})${broken.length ? '  IMAGE MISSING' : ''}`);
}
await browser.close();
fs.rmSync(tmp, { recursive: true, force: true });
console.log(`\n${report.length} images in ${outDir}`);
