<!-- ================= HERO ================= -->
<section id="accueil" class="bg-mr-bg overflow-hidden">
  <div class="max-w-7xl mx-auto px-4 sm:px-5 md:px-8 pt-8 md:pt-20 pb-10 md:pb-24">

    <!-- Grid toujours en 2 colonnes, même sur mobile -->
    <div class="grid grid-cols-2 gap-4 sm:gap-6 md:gap-10 items-center">

      <!-- Colonne texte -->
      <div>
        <span class="inline-flex items-center gap-1.5 bg-mr-blue-pale text-mr-blue-dark text-[10px] sm:text-xs font-bold px-2.5 sm:px-3.5 py-1 sm:py-1.5 rounded-full mb-3 sm:mb-6">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" class="shrink-0"><path d="M17 20c0-2.8-2.2-5-5-5s-5 2.2-5 5M12 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19.5 19c0-2-1.6-3.6-3.5-4M16.5 8.3a2.6 2.6 0 1 1 0-5.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span class="whitespace-nowrap">Gagnez avec le digital</span>
        </span>

        <h1 class="text-2xl sm:text-3xl md:text-5xl font-extrabold leading-[1.1] tracking-tight text-mr-navy mb-3 sm:mb-5">
          Recommandez,<br/>
          Gagnez,<br/>
          <span class="text-mr-blue">Grandissez.</span>
        </h1>

        <p class="text-[13px] sm:text-base md:text-lg text-mr-navy-soft leading-relaxed max-w-md mb-5 sm:mb-8">
          MonRevenu est une plateforme d'affiliation qui vous permet de promouvoir des produits et services fiables, et de toucher des commissions à chaque vente.
        </p>

        <div class="flex flex-col gap-2.5 sm:gap-3 mb-5 sm:mb-7">
          <button type="button" onclick="openModal('modal-register')"
                  class="inline-flex items-center justify-center gap-2 bg-mr-blue hover:bg-mr-blue-dark text-white font-bold text-[13px] sm:text-sm px-5 sm:px-6 py-3 sm:py-3.5 rounded-full transition-colors w-full sm:w-auto">
            Créer mon compte gratuit
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="shrink-0"><path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <a href="#comment"
             class="inline-flex items-center justify-center gap-2 bg-white border border-slate-200 hover:border-mr-blue text-mr-navy font-bold text-[13px] sm:text-sm px-5 sm:px-6 py-3 sm:py-3.5 rounded-full transition-colors w-full sm:w-auto">
            Découvrir la plateforme
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" class="shrink-0"><path d="M8 5v14l11-7L8 5Z"/></svg>
          </a>
        </div>
      </div>

      <!-- Colonne téléphone mockup -->
      <div class="relative flex justify-center sm:justify-end">
        <div class="absolute -z-10 top-0 right-0 w-40 sm:w-64 h-40 sm:h-64 rounded-full bg-mr-blue-pale blur-2xl opacity-70"></div>

        <!-- Cadre téléphone -->
        <div class="relative w-[130px] sm:w-[220px] md:w-[260px] aspect-[9/19] bg-black rounded-[1.4rem] sm:rounded-[2.2rem] p-[6px] sm:p-[10px] shadow-xl">
          <div class="w-full h-full bg-white rounded-[1rem] sm:rounded-[1.6rem] overflow-hidden flex flex-col">

            <!-- Mini header du téléphone -->
            <div class="flex items-center gap-1 px-2 sm:px-3 py-2 sm:py-2.5 border-b border-slate-100">
              <span class="w-3.5 h-3.5 sm:w-5 sm:h-5 rounded-md bg-mr-blue flex items-center justify-center text-white font-extrabold text-[7px] sm:text-[10px]">M</span>
              <span class="text-[7px] sm:text-[10px] font-extrabold text-mr-navy">MonRevenu</span>
            </div>

            <div class="p-2 sm:p-3 flex-1 overflow-hidden">
              <!-- Carte gains -->
              <div class="bg-mr-blue rounded-lg sm:rounded-xl p-2 sm:p-3 mb-2">
                <p class="text-[6px] sm:text-[9px] text-white/70 font-semibold mb-0.5">Mes gains</p>
                <p class="text-[10px] sm:text-base font-extrabold text-white">73 500 F</p>
                <p class="text-[6px] sm:text-[9px] text-sky-200 font-bold mt-0.5">↑ +12% ce mois</p>
              </div>

              <!-- Ventes / Commissions -->
              <div class="grid grid-cols-2 gap-1.5 sm:gap-2 mb-2">
                <div class="bg-mr-bg rounded-lg p-1.5 sm:p-2 border border-slate-100">
                  <p class="text-[5.5px] sm:text-[8px] font-semibold text-mr-navy-soft mb-0.5">Ventes</p>
                  <p class="text-[9px] sm:text-sm font-extrabold text-mr-navy">147</p>
                  <p class="text-[5.5px] sm:text-[8px] font-bold text-mr-blue-dark">↑ +18%</p>
                </div>
                <div class="bg-mr-bg rounded-lg p-1.5 sm:p-2 border border-slate-100">
                  <p class="text-[5.5px] sm:text-[8px] font-semibold text-mr-navy-soft mb-0.5">Commissions</p>
                  <p class="text-[9px] sm:text-sm font-extrabold text-mr-navy">73 500</p>
                  <p class="text-[5.5px] sm:text-[8px] font-bold text-mr-blue-dark">↑ +12%</p>
                </div>
              </div>

              <!-- Graphique -->
              <div class="bg-mr-bg rounded-lg p-1.5 sm:p-2 border border-slate-100">
                <p class="text-[5.5px] sm:text-[8px] font-semibold text-mr-navy-soft mb-1">Évolution</p>
                <svg viewBox="0 0 120 30" class="w-full h-6 sm:h-8">
                  <polyline points="0,24 20,20 40,22 60,12 80,15 100,6 120,4" fill="none" stroke="#1465e0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  <polygon points="0,24 20,20 40,22 60,12 80,15 100,6 120,4 120,30 0,30" fill="url(#heroChartFillMini)"/>
                  <defs><linearGradient id="heroChartFillMini" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1465e0" stop-opacity=".25"/><stop offset="1" stop-color="#1465e0" stop-opacity="0"/></linearGradient></defs>
                </svg>
              </div>
            </div>

            <!-- Barre de nav basse -->
            <div class="flex items-center justify-around py-1.5 sm:py-2 border-t border-slate-100">
              <span class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded bg-mr-blue"></span>
              <span class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded bg-slate-200"></span>
              <span class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded bg-slate-200"></span>
              <span class="w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 rounded bg-slate-200"></span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>