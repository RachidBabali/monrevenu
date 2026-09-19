// tools/controle-debordement.js : aucun element ne doit deborder horizontalement des feuilles de connexion
// et d'inscription sur mobile (etats : ouverte, erreurs affichees, clavier ouvert, iframe Google simulee).
// Usage local, serveur de dev lance et Playwright installe hors du depot :
//   NODE_PATH=<dossier>/node_modules BASE=http://127.0.0.1:8000 node tools/controle-debordement.js
// Code de sortie 1 si un element depasse la fenetre, si la feuille defile horizontalement ou si une erreur JS survient.
const { chromium, webkit } = require('playwright');
let echecs = 0;
const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const largeurs = (process.env.W || '360,375,390,393').split(',').map(Number);
const moteur = process.env.MOTEUR || 'chromium';
const mesure = () => {
  const vw = document.documentElement.clientWidth;
  const d = document.querySelector('dialog[open]');
  const hors = [];
  document.querySelectorAll('body *').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.width && (r.right > vw + 0.5 || r.left < -0.5) && getComputedStyle(el).visibility !== 'hidden') {
      hors.push((el.id ? '#' + el.id : el.tagName.toLowerCase() + '.' + String(el.className).split(' ').slice(0, 2).join('.')) + ' right=' + Math.round(r.right) + ' w=' + Math.round(r.width));
    }
  });
  return { vw, docScroll: document.documentElement.scrollWidth, dialog: d ? d.id : null,
    dialogScroll: d ? d.scrollWidth + '/' + d.clientWidth : null,
    corpsScroll: d ? (d.querySelector('.feuille-corps').scrollWidth + '/' + d.querySelector('.feuille-corps').clientWidth) : null,
    google: [...document.querySelectorAll('[data-bloc-google]')].map(b => b.hidden ? 'cache' : 'visible').join(','),
    defilables: d ? [...d.querySelectorAll('*')].filter(e => e.scrollWidth > e.clientWidth + 1 && e.clientWidth > 0 && !['INPUT','SELECT'].includes(e.tagName)).map(e => e.tagName + '.' + String(e.className).split(' ')[0] + ' ' + e.scrollWidth + '/' + e.clientWidth) : [],
    // Largeur minimale reelle des champs (min-content), pour estimer le risque sur WebKit
    minChamps: d ? [...d.querySelectorAll('.champ-groupe, input[type=date]')].map(e => { const c = e.cloneNode(true); c.style.cssText = 'position:absolute;visibility:hidden;width:min-content'; document.body.appendChild(c); const w = c.getBoundingClientRect().width; c.remove(); return (e.id || e.className.split(' ')[0]) + ' min=' + Math.round(w); }) : [],
    largeurBoutonGoogle: [...document.querySelectorAll('[id^=googleBtn] .nsm7Bb-HzV7m-LgbsSe, [id^=googleBtn] iframe')].map(e => Math.round(e.getBoundingClientRect().width)).join(','),
    hors: hors.slice(0, 12) };
};
(async () => {
  const nav = moteur === 'webkit' ? await webkit.launch() : await chromium.launch({ channel: 'chrome' });
  for (const w of largeurs) {
    for (const [hash, etat] of [['#inscription', 'ouverte'], ['#inscription', 'erreurs'], ['#inscription', 'clavier'], ['#inscription', 'iframe-simulee'], ['#connexion', 'ouverte'], ['#connexion', 'iframe-simulee']]) {
      const ctx = await nav.newContext({ viewport: { width: w, height: etat === 'clavier' ? 380 : 780 }, isMobile: moteur !== 'webkit' || true, hasTouch: true, deviceScaleFactor: 2 });
      const p = await ctx.newPage();
      const erreursJs = [];
      p.on('pageerror', e => erreursJs.push(e.message));
      await p.goto(BASE + '/?t=' + Date.now() + hash, { waitUntil: 'load' });
      await p.waitForTimeout(3500);
      if (etat === 'erreurs') { await p.click('#registerForm button[type=submit]'); await p.waitForTimeout(300); }
      if (etat === 'clavier') { await p.focus('#phone'); await p.waitForTimeout(300); }
      if (etat === 'iframe-simulee') {
        // Reproduit la geometrie de l'iframe GSI : largeur demandee + marges negatives de 10 px de chaque cote
        await p.evaluate(() => document.querySelectorAll('[id^=googleBtn]').forEach(el => {
          const b = el.closest('[data-bloc-google]'); if (b) b.hidden = false;
          el.innerHTML = '<div><iframe style="width:' + 320 + 'px;height:44px;margin:-2px -10px;border:0;display:block" title="simu"></iframe></div>';
        }));
        await p.waitForTimeout(200);
      }
      const m = await p.evaluate(mesure);
      const ko = m.docScroll > m.vw || m.hors.length > 0 || erreursJs.length > 0 ||
        (etat !== 'iframe-simulee' && m.defilables.some(x => !x.includes('sr-only')));
      if (ko) echecs++;
      console.log((ko ? 'ECHEC ' : 'ok    ') + JSON.stringify({ moteur, w, hash, etat, doc: m.docScroll, bouton: m.largeurBoutonGoogle, hors: m.hors, defilables: m.defilables, erreursJs }));
      await ctx.close();
    }
  }
  await nav.close();
  process.exit(echecs ? 1 : 0);
})();
