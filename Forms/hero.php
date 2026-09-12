<!-- ================= HERO ================= -->
<section id="accueil" class="bg-mr-bg">
  <div class="max-w-7xl mx-auto px-5 md:px-8 pt-14 md:pt-20 pb-16 md:pb-24 grid md:grid-cols-2 gap-12 md:gap-10 items-center">

    <!-- Colonne texte -->
    <div>
      <span class="inline-flex items-center gap-2 bg-mr-blue-pale text-mr-blue-dark text-xs font-bold px-3.5 py-1.5 rounded-full mb-6">
        <span class="w-1.5 h-1.5 rounded-full bg-mr-blue"></span>
        Plateforme d'affiliation
      </span>

      <h1 class="text-3xl md:text-5xl font-extrabold leading-tight tracking-tight text-mr-navy mb-5">
        Recommandez.<br/>
        Gagnez.<br/>
        <span class="text-mr-blue">Développez vos revenus.</span>
      </h1>

      <p class="text-base md:text-lg text-mr-navy-soft leading-relaxed max-w-md mb-8">
        MonRevenu vous permet de promouvoir des produits et services, partager vos liens d'affiliation et gagner des commissions grâce à vos recommandations.
      </p>

      <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 mb-7">
        <button type="button" onclick="openModal('modal-register')" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-mr-blue hover:bg-mr-blue-dark text-white font-bold text-sm px-6 py-3.5 rounded-full transition-colors">
          Commencer gratuitement
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <a href="#comment" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white border border-slate-200 hover:border-mr-blue text-mr-navy font-bold text-sm px-6 py-3.5 rounded-full transition-colors">
          Découvrir comment ça marche
        </a>
      </div>

      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-semibold text-mr-navy-soft">
        <span class="inline-flex items-center gap-1.5">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="text-mr-blue"><path d="M8 12l3 3 5-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Simple
        </span>
        <span class="inline-flex items-center gap-1.5">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="text-mr-blue"><path d="M8 12l3 3 5-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Sécurisé
        </span>
        <span class="inline-flex items-center gap-1.5">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="text-mr-blue"><path d="M8 12l3 3 5-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Accessible partout
        </span>
      </div>
    </div>

    <!-- Colonne mockup dashboard affiliation -->
    <div class="relative">
      <div class="absolute -z-10 -top-6 -right-6 w-64 h-64 rounded-full bg-mr-blue-pale blur-2xl opacity-70"></div>

      <div class="bg-white rounded-2xl shadow-mr-card border border-slate-100 p-5 md:p-6 max-w-md md:ml-auto">
        <div class="flex items-center justify-between mb-5">
          <div>
            <p class="text-xs font-semibold text-mr-navy-soft">Mon espace affilié</p>
            <p class="text-sm font-bold text-mr-navy">Aperçu — valeurs d'exemple</p>
          </div>
          <span class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-200 to-rose-200"></span>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
          <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100">
            <p class="text-[11px] font-semibold text-mr-navy-soft mb-1">Mes commissions</p>
            <p class="text-lg font-extrabold text-mr-navy">120 000 F</p>
            <p class="text-[11px] font-bold text-mr-blue-dark mt-0.5">↑ exemple</p>
          </div>
          <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100">
            <p class="text-[11px] font-semibold text-mr-navy-soft mb-1">Solde disponible</p>
            <p class="text-lg font-extrabold text-mr-navy">65 000 F</p>
            <p class="text-[11px] font-semibold text-mr-navy-soft mt-0.5">à retirer</p>
          </div>
          <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100">
            <p class="text-[11px] font-semibold text-mr-navy-soft mb-1">Clics</p>
            <p class="text-lg font-extrabold text-mr-navy">—</p>
          </div>
          <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100">
            <p class="text-[11px] font-semibold text-mr-navy-soft mb-1">Conversion</p>
            <p class="text-lg font-extrabold text-mr-navy">—</p>
          </div>
        </div>

        <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100 mb-4">
          <p class="text-[11px] font-semibold text-mr-navy-soft mb-2">Évolution des commissions</p>
          <svg viewBox="0 0 260 50" class="w-full h-12">
            <polyline points="0,40 40,34 80,36 120,22 160,26 200,12 240,8" fill="none" stroke="#1465e0" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
            <polygon points="0,40 40,34 80,36 120,22 160,26 200,12 240,8 240,50 0,50" fill="url(#heroChartFill)"/>
            <defs><linearGradient id="heroChartFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1465e0" stop-opacity=".2"/><stop offset="1" stop-color="#1465e0" stop-opacity="0"/></linearGradient></defs>
          </svg>
        </div>

        <div class="bg-mr-bg rounded-xl p-3.5 border border-slate-100">
          <p class="text-[11px] font-semibold text-mr-navy-soft mb-2">Dernières commissions</p>
          <div class="flex items-center justify-between text-xs py-1.5">
            <span class="font-semibold text-mr-navy">Offre e-commerce</span>
            <span class="font-bold text-mr-blue-dark">+ exemple</span>
          </div>
          <div class="flex items-center justify-between text-xs py-1.5 border-t border-slate-100">
            <span class="font-semibold text-mr-navy">Offre formation</span>
            <span class="font-bold text-mr-blue-dark">+ exemple</span>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>