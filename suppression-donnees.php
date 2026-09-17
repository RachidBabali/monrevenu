<?php
/**
 * suppression-donnees.php — Page publique de suppression des données
 * Exigée par Meta pour l'app WhatsApp Business. Accessible sans connexion.
 * À placer à la racine du projet.
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suppression des données — MonRevenu</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          display: ['"Sora"', 'sans-serif'],
          sans: ['"Inter"', 'sans-serif']
        },
        colors: {
          ink: { DEFAULT: '#12213D', soft: '#3A4A6B' },
          paper: '#F5F7FB',
          line: '#E1E6F0',
          brand: { DEFAULT: '#1E3F8F', dark: '#152C66', light: '#2F62D6', soft: '#E8EEFC' },
          ok: { DEFAULT: '#137A55', soft: '#E3F5EC' },
          warn: { DEFAULT: '#B4720F', soft: '#FBF0DA' }
        }
      }
    }
  }
</script>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<style>
  body { font-family: 'Inter', sans-serif; }
  .font-display { font-family: 'Sora', sans-serif; }
</style>
</head>
<body class="bg-paper text-ink min-h-screen">

<div class="max-w-3xl mx-auto px-5 md:px-8 py-10 md:py-14">

  <a href="/index.php" class="inline-flex items-center gap-1.5 text-brand font-semibold text-[13px] mb-8 hover:underline">
    ← Retour à MonRevenu
  </a>

  <h1 class="font-display font-bold text-[26px] md:text-[30px] text-ink mb-1">Suppression de vos données</h1>
  <p class="text-ink/45 text-[13px] mb-8">Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <div class="bg-white rounded-2xl border border-line p-6 md:p-8 flex flex-col gap-7">

    <section>
      <h2 class="font-display font-bold text-[16px] text-brand mb-2">Quelles données sont collectées</h2>
      <p class="text-[13.5px] text-ink/70 leading-relaxed mb-3">Lorsque vous créez un compte et utilisez MonRevenu, nous collectons :</p>
      <ul class="list-disc pl-5 text-[13.5px] text-ink/70 leading-relaxed space-y-1">
        <li>Votre nom complet et votre adresse email</li>
        <li>Votre numéro de téléphone WhatsApp, utilisé pour vérifier votre identité</li>
        <li>Votre historique de ventes, de commissions et de retraits</li>
      </ul>
    </section>

    <section>
      <h2 class="font-display font-bold text-[16px] text-brand mb-2">Pourquoi ces données</h2>
      <p class="text-[13.5px] text-ink/70 leading-relaxed">
        Elles servent à créer et sécuriser votre compte, vérifier votre numéro WhatsApp, calculer et verser vos commissions
        d'affiliation, et traiter vos demandes de retrait.
      </p>
    </section>

    <section>
      <h2 class="font-display font-bold text-[16px] text-brand mb-2">Combien de temps sont-elles conservées</h2>
      <p class="text-[13.5px] text-ink/70 leading-relaxed">
        Tant que votre compte est actif. Si vous demandez la suppression de votre compte, vos données personnelles
        (nom, email, numéro de téléphone, code de vérification WhatsApp) sont effacées sous 30 jours.
      </p>
    </section>

    <section>
      <h2 class="font-display font-bold text-[16px] text-brand mb-2">Ce qui est conservé malgré la suppression</h2>
      <p class="text-[13.5px] text-ink/70 leading-relaxed">
        Pour des raisons comptables et légales, l'historique de vos ventes, commissions et retraits déjà effectués est
        conservé, mais il est dissocié de votre identité : votre nom, email et téléphone ne restent associés à aucune
        de ces lignes après suppression.
      </p>
    </section>

    <section>
      <h2 class="font-display font-bold text-[16px] text-brand mb-3">Comment demander la suppression</h2>
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="bg-brand-soft rounded-xl p-4">
          <p class="font-display font-semibold text-[13.5px] text-ink mb-1">Depuis votre compte</p>
          <p class="text-[12.5px] text-ink/60 leading-relaxed mb-2">
            Connectez-vous, ouvrez votre profil, puis « Supprimer mon compte » en bas de page.
          </p>
          <a href="/page/profil.php#supprimer-compte" class="text-[12.5px] font-semibold text-brand hover:underline">Aller à mon profil →</a>
        </div>
        <div class="bg-warn-soft rounded-xl p-4">
          <p class="font-display font-semibold text-[13.5px] text-ink mb-1">Par email</p>
          <p class="text-[12.5px] text-ink/60 leading-relaxed mb-2">
            Écrivez-nous en précisant le numéro de téléphone ou l'email associé au compte à supprimer.
          </p>
          <a href="mailto:contact@monrevenu.xyz?subject=Demande%20de%20suppression%20de%20compte" class="text-[12.5px] font-semibold text-warn hover:underline">contact@monrevenu.xyz</a>
        </div>
      </div>
      <p class="text-[12px] text-ink/45 mt-3">Délai de traitement annoncé : sous 30 jours à compter de la demande.</p>
    </section>

  </div>

</div>

</body>
</html>
