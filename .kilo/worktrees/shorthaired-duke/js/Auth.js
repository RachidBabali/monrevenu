/* ══════════════════════════════════════════
   SÉCURITÉ — BLOCAGE INSPECTEUR & CLIC DROIT
   ══════════════════════════════════════════ */

// 1. Bloquer le menu contextuel (Clic droit)
document.addEventListener('contextmenu', e => e.preventDefault());

// 2. Bloquer les raccourcis DevTools
document.addEventListener('keydown', e => {
  const isMeta  = e.ctrlKey || e.metaKey;
  const isShift = e.shiftKey;

  // F12
  if (e.key === 'F12') {
    e.preventDefault();
    return false;
  }

  // Ctrl+Shift+I — Inspecter
  if (isMeta && isShift && e.key.toUpperCase() === 'I') {
    e.preventDefault();
    return false;
  }

  // Ctrl+Shift+J — Console
  if (isMeta && isShift && e.key.toUpperCase() === 'J') {
    e.preventDefault();
    return false;
  }

  // Ctrl+Shift+C — Sélecteur d'élément
  if (isMeta && isShift && e.key.toUpperCase() === 'C') {
    e.preventDefault();
    return false;
  }

  // Ctrl+U — Voir le code source
  if (isMeta && e.key.toUpperCase() === 'U') {
    e.preventDefault();
    return false;
  }

  // Ctrl+S — Enregistrer la page
  if (isMeta && e.key.toUpperCase() === 'S') {
    e.preventDefault();
    return false;
  }
});