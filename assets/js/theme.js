// Charge en <head>, avant le rendu : applique le theme memorise sans flash.
try {
  if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
} catch (e) {}
