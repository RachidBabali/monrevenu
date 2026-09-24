// Charge en <head>, avant le rendu : applique le theme memorise sans flash.
try {
  if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
} catch (e) {}
// Fuseau horaire du navigateur, lu cote serveur pour le score de coherence du pays (signal declaratif, pas une preuve).
try { document.cookie = 'mr_tz=' + encodeURIComponent(Intl.DateTimeFormat().resolvedOptions().timeZone || '') + ';path=/;max-age=31536000;SameSite=Lax'; } catch (e) {}
