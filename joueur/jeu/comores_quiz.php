<?php
session_start();
require_once '../../basse_de_donner/monrevenu_bd.php';

// Sécurité
if (!isset($_SESSION['user_id'])) { header("Location: /index.php"); exit(); }
if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) { session_destroy(); header("Location: /index.php?error=session"); exit(); }
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 7200)) { session_destroy(); header("Location: /index.php?error=expired"); exit(); }
$_SESSION['login_time'] = time();

$user_id = (int) $_SESSION['user_id'];

// Mise fixe du jeu — jamais lue depuis le client, ni ici ni côté API.
define('MISE_FIXE', 100);
define('MULTIPLICATEUR_GAIN', 5);

// Solde initial
$stmt = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ?");
$stmt->execute([$user_id]);
$balance = (int) $stmt->fetchColumn();

// ---------- API AJAX ----------
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'get_balance') {
        echo json_encode(['balance' => $balance]);
        exit;
    }

    if ($action === 'start') {
        // La mise est fixe (100 KMF) : on ignore toute valeur envoyée par le client.
        $mise = MISE_FIXE;

        $pdo->beginTransaction();
        $check = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ? FOR UPDATE");
        $check->execute([$user_id]);
        $current_balance = (int) $check->fetchColumn();
        if ($current_balance < $mise) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Solde insuffisant']);
            exit;
        }
        $update = $pdo->prepare("UPDATE users_monrevenu SET balance = balance - ? WHERE id = ?");
        $update->execute([$mise, $user_id]);
        $_SESSION['quiz_mise'] = $mise;
        $pdo->commit();
        echo json_encode(['success' => true, 'balance' => $current_balance - $mise, 'mise' => $mise]);
        exit;
    }

    if ($action === 'win') {
        if (!isset($_SESSION['quiz_mise'])) {
            echo json_encode(['error' => 'Aucune partie en cours']);
            exit;
        }
        $mise = (int) $_SESSION['quiz_mise'];
        $gain = $mise * MULTIPLICATEUR_GAIN;
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE users_monrevenu SET balance = balance + ? WHERE id = ?");
        $update->execute([$gain, $user_id]);
        unset($_SESSION['quiz_mise']);
        $pdo->commit();
        $stmt = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ?");
        $stmt->execute([$user_id]);
        $new_balance = (int) $stmt->fetchColumn();
        echo json_encode(['success' => true, 'balance' => $new_balance, 'gain' => $gain]);
        exit;
    }

    if ($action === 'lose') {
        if (isset($_SESSION['quiz_mise'])) unset($_SESSION['quiz_mise']);
        $stmt = $pdo->prepare("SELECT balance FROM users_monrevenu WHERE id = ?");
        $stmt->execute([$user_id]);
        $new_balance = (int) $stmt->fetchColumn();
        echo json_encode(['success' => true, 'balance' => $new_balance]);
        exit;
    }

    echo json_encode(['error' => 'Action inconnue']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>MonRevenu Quiz | x5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        (function () {
            var theme = localStorage.getItem('theme');
            if (theme === 'dark') document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        })();
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sora: ['Sora', 'sans-serif'] },
                    colors: {
                        brand: { DEFAULT: '#1246A0', mid: '#1A5FCC', light: '#3B82F6', soft: '#EEF4FF' }
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sora', sans-serif; }
        html, body {
            overflow: hidden;
            height: 100dvh;
            width: 100%;
            position: fixed;
        }
        * { -webkit-tap-highlight-color: transparent; user-select: none; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .blink { animation: blink 0.5s ease infinite; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
        .slide { animation: slideIn 0.18s ease forwards; }

        /* Tout doit tenir sur l'écran sans scroll : on compacte
           progressivement les espacements et tailles de police selon
           la hauteur disponible, plutôt que de laisser déborder. */
        #app {
            height: 100dvh;
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            gap: 8px;
            overflow: hidden;
        }
        #answers { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; gap: 8px; justify-content: center; }
        .a-btn { flex: 1 1 auto; min-height: 0; }
        #question-banner { flex-shrink: 0; }

        @media (max-height: 780px) {
            #app { padding: 8px 10px; gap: 6px; }
            #question-banner { padding: 12px 16px !important; }
            #q-text { font-size: 15px !important; }
            .a-btn { padding: 10px 12px !important; }
            .a-ltr { width: 30px !important; height: 30px !important; font-size: 12px !important; }
            .a-txt { font-size: 12px !important; }
        }
        @media (max-height: 680px) {
            #app { padding: 6px 8px; gap: 5px; }
            .stat { padding: 5px !important; }
            #mise-card { padding: 10px 14px !important; }
            #question-banner { padding: 10px 14px !important; }
            #q-text { font-size: 13px !important; }
            .a-btn { padding: 8px 10px !important; }
            .a-ltr { width: 26px !important; height: 26px !important; font-size: 11px !important; }
            .a-txt { font-size: 11px !important; }
        }
        @media (max-height: 580px) {
            #app { padding: 5px 7px; gap: 4px; }
            #mise-card p.font-extrabold { font-size: 13px !important; }
            #question-banner { padding: 8px 12px !important; }
            #q-text { font-size: 12px !important; }
            .a-btn { padding: 6px 9px !important; gap: 8px !important; }
            .a-ltr { width: 22px !important; height: 22px !important; font-size: 10px !important; }
            .a-txt { font-size: 10px !important; }
            .stat .s-lbl-text { display: none; }
        }
    </style>
</head>
<body class="bg-[#F8F9FB] dark:bg-[#0B1120] text-slate-900 dark:text-slate-100 h-full">

<div id="app">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-y-2">
        <div class="flex items-center gap-2.5">
            <a href="../../dashboard.php" id="backLink"
               class="flex items-center gap-1.5 bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-slate-500 hover:text-brand hover:border-brand/30 transition-colors text-[11px] font-semibold shadow-sm shrink-0">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                <span class="hidden sm:inline">Retour</span>
            </a>
            <div class="flex items-center gap-1.5 shrink-0">
                <div class="w-7 h-7 rounded-lg bg-brand flex items-center justify-center text-white font-extrabold text-[12px]">M</div>
                <span class="font-extrabold text-[13px] sm:text-[14px] text-slate-800 dark:text-white whitespace-nowrap">Quiz <span class="text-slate-400 font-semibold">x5</span></span>
            </div>
        </div>
        <div class="flex items-center gap-1.5 bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-full px-3 py-1.5 shadow-sm shrink-0">
            <span class="text-[9px] text-slate-400 font-semibold">SOLDE</span>
            <span id="wallet-amount" class="font-mono font-extrabold text-[12px] text-brand dark:text-blue-400"><?= number_format($balance, 0, ',', '.') ?></span>
            <span class="text-[9px] text-slate-400">KMF</span>
        </div>
    </div>

    <!-- Barre de temps -->
    <div class="h-1 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden shrink-0">
        <div id="t-fill" class="h-full bg-brand transition-all duration-1000" style="width:100%"></div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-2">
        <div class="bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-xl px-2 py-2 text-center shadow-sm">
            <p class="text-[8px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Question</p>
            <p id="s-q" class="font-mono font-semibold text-[13px] text-slate-800 dark:text-white">—</p>
        </div>
        <div class="bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-xl px-2 py-2 text-center shadow-sm">
            <p class="text-[8px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Mise</p>
            <p id="s-m" class="font-mono font-semibold text-[13px] text-brand dark:text-blue-400">—</p>
        </div>
        <div class="bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-xl px-2 py-2 text-center shadow-sm">
            <p class="text-[8px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Gain max</p>
            <p id="s-g" class="font-mono font-semibold text-[13px] text-emerald-500">—</p>
        </div>
    </div>

    <!-- Zone de mise (fixe, 100 KMF) -->
    <div id="mise-card" class="shrink-0 bg-white dark:bg-[#141E33] border border-slate-100 dark:border-slate-800 rounded-2xl px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-0.5">Mise fixe</p>
                <p class="font-mono font-extrabold text-[16px] text-slate-800 dark:text-white">100 KMF <span class="text-emerald-500 text-[11px] font-semibold">→ gain 500 KMF</span></p>
            </div>
            <button id="btn-l" class="shrink-0 bg-brand hover:bg-brand-mid text-white font-extrabold text-[11px] uppercase tracking-wide rounded-xl px-5 py-2.5 transition-all active:scale-95 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                Jouer
            </button>
        </div>
        <p id="mise-info" class="text-[10px] text-slate-400 font-medium mt-2 text-center">Chargement des questions...</p>
    </div>

    <!-- Progression des questions -->
    <div class="h-1 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden shrink-0">
        <div id="prog-bar" class="h-full bg-emerald-500 transition-all duration-300" style="width:0%"></div>
    </div>

    <!-- Carte question — façon jeu télévisé -->
    <div id="question-banner" class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand via-brand-mid to-brand-light px-5 py-4 shadow-lg shrink-0">
        <div class="absolute -right-6 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
        <div class="absolute right-10 bottom-[-30px] w-20 h-20 rounded-full bg-white/5"></div>
        <span id="q-badge" class="relative inline-block bg-white/20 text-white text-[9px] font-extrabold uppercase tracking-widest rounded-full px-3 py-1 mb-2">Question ?</span>
        <p id="q-text" class="relative font-extrabold text-[16px] sm:text-[18px] text-white leading-snug italic font-normal text-white/70">En attente de votre mise…</p>
    </div>

    <!-- Réponses — liste verticale, pastille lettrée façon quiz TV -->
    <div id="answers" class="flex-1 flex flex-col gap-2">
        <button class="a-btn flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-100 dark:border-slate-800 rounded-2xl px-3 py-3 opacity-60" disabled>
            <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-slate-400">A</div>
            <div class="a-txt text-[13px] font-semibold text-slate-500">—</div>
        </button>
        <button class="a-btn flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-100 dark:border-slate-800 rounded-2xl px-3 py-3 opacity-60" disabled>
            <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-slate-400">B</div>
            <div class="a-txt text-[13px] font-semibold text-slate-500">—</div>
        </button>
        <button class="a-btn flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-100 dark:border-slate-800 rounded-2xl px-3 py-3 opacity-60" disabled>
            <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-slate-400">C</div>
            <div class="a-txt text-[13px] font-semibold text-slate-500">—</div>
        </button>
        <button class="a-btn flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-100 dark:border-slate-800 rounded-2xl px-3 py-3 opacity-60" disabled>
            <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-slate-400">D</div>
            <div class="a-txt text-[13px] font-semibold text-slate-500">—</div>
        </button>
    </div>
</div>

<!-- Résultat (overlay) -->
<div id="result" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex-col items-center justify-center px-6 text-center gap-3">
    <div id="r-icon" class="text-5xl">🏆</div>
    <div id="r-title" class="font-extrabold text-[26px]">VICTOIRE !</div>
    <div id="r-gain" class="font-mono font-bold text-[20px] text-amber-500"></div>
    <p id="r-reason" class="text-[12px] text-slate-300 max-w-[260px]"></p>
    <button id="btn-replay" class="mt-1 bg-white/10 border border-white/20 text-white font-extrabold text-[11px] uppercase tracking-wide rounded-xl px-7 py-3 hover:bg-white/20 transition-all active:scale-95">
        Nouvelle partie
    </button>
</div>

<script>
    const TOTAL = 8;
    const TIME = 80;
    const MISE_FIXE = 100;
    const GAIN_FIXE = MISE_FIXE * 5;
    const LTR = ['A', 'B', 'C', 'D'];

    let allQuestions = [];
    let selectedQuestions = [];
    let currentIndex = 0;
    let timeLeft = TIME;
    let timerInterval = null;
    let gameActive = false;
    let wallet = <?= (int) $balance ?>;

    const walletSpan = document.getElementById('wallet-amount');
    const questionText = document.getElementById('q-text');
    const questionBadge = document.getElementById('q-badge');
    const answersDiv = document.getElementById('answers');
    const btnLaunch = document.getElementById('btn-l');
    const miseInfo = document.getElementById('mise-info');
    const sQ = document.getElementById('s-q');
    const sM = document.getElementById('s-m');
    const sG = document.getElementById('s-g');
    const progBar = document.getElementById('prog-bar');
    const tFill = document.getElementById('t-fill');
    const resultDiv = document.getElementById('result');
    const rIcon = document.getElementById('r-icon');
    const rTitle = document.getElementById('r-title');
    const rGain = document.getElementById('r-gain');
    const rReason = document.getElementById('r-reason');

    function updateWalletUI() {
        walletSpan.textContent = wallet.toLocaleString('fr');
    }

    async function apiCall(action, data = {}) {
        const formData = new URLSearchParams();
        for (let [k, v] of Object.entries(data)) formData.append(k, v);
        const res = await fetch(`?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        return res.json();
    }

    // Blocage retour navigateur
    function blockBackNavigation() {
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function () {
            if (gameActive) {
                history.pushState(null, null, location.href);
                const toast = document.createElement('div');
                toast.textContent = '⛔ Retour arrière désactivé pendant la partie';
                toast.className = 'fixed bottom-5 left-1/2 -translate-x-1/2 bg-red-500 text-white px-4 py-2 rounded-full text-[12px] font-mono z-[999] opacity-90';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            } else {
                window.location.href = 'dashboard_jeu.php';
            }
        });
    }
    blockBackNavigation();

    window.addEventListener('beforeunload', (e) => {
        if (gameActive) {
            e.preventDefault();
            e.returnValue = '⚠️ Partie en cours, votre mise ne sera pas remboursée.';
            return e.returnValue;
        }
    });

    document.getElementById('backLink').addEventListener('click', async (e) => {
        if (gameActive) {
            e.preventDefault();
            if (confirm('⚠️ Une partie est en cours. Si vous quittez, vous perdez votre mise. Voulez-vous vraiment continuer ?')) {
                await apiCall('lose');
                window.location.href = e.currentTarget.href;
            }
        }
    });

    async function loadQuestions() {
        try {
            const res = await fetch('questions.json');
            if (!res.ok) throw new Error();
            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) throw new Error();
            allQuestions = data;
            miseInfo.textContent = `✅ ${allQuestions.length} questions chargées. Prêt à jouer !`;
            miseInfo.classList.remove('text-red-500');
            miseInfo.classList.add('text-emerald-500');
            btnLaunch.disabled = false;
        } catch (err) {
            miseInfo.textContent = '❌ Erreur : questions.json introuvable.';
            miseInfo.classList.add('text-red-500');
            btnLaunch.disabled = true;
            questionText.textContent = 'Fichier de questions manquant.';
        }
    }

    function shuffleArray(arr) {
        for (let i = arr.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [arr[i], arr[j]] = [arr[j], arr[i]];
        }
        return arr;
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    async function startGame() {
        if (!allQuestions.length) {
            miseInfo.textContent = '❌ Chargement des questions...';
            return;
        }
        if (MISE_FIXE > wallet) {
            miseInfo.textContent = `⚠ Solde insuffisant (${wallet.toLocaleString('fr')} KMF)`;
            miseInfo.classList.add('text-red-500');
            return;
        }

        const result = await apiCall('start');
        if (result.error) {
            miseInfo.textContent = '❌ ' + result.error;
            miseInfo.classList.add('text-red-500');
            return;
        }

        wallet = result.balance;
        updateWalletUI();
        gameActive = true;

        btnLaunch.disabled = true;
        miseInfo.textContent = `Mise engagée : ${MISE_FIXE} KMF → gain max ${GAIN_FIXE} KMF`;
        miseInfo.classList.remove('text-red-500', 'text-emerald-500');
        sM.textContent = MISE_FIXE + ' KMF';
        sG.textContent = GAIN_FIXE + ' KMF';

        const shuffledAll = shuffleArray([...allQuestions]);
        selectedQuestions = shuffledAll.slice(0, TOTAL);
        currentIndex = 0;
        timeLeft = TIME;
        tFill.style.width = '100%';
        tFill.classList.remove('bg-red-500');
        tFill.classList.add('bg-brand');
        renderCurrentQuestion();
        startTimer();
    }

    function renderCurrentQuestion() {
        const q = selectedQuestions[currentIndex];
        const percent = Math.round((currentIndex / TOTAL) * 100);
        progBar.style.width = percent + '%';
        sQ.textContent = (currentIndex + 1) + '/' + TOTAL;
        questionBadge.textContent = 'Question ' + (currentIndex + 1) + ' / ' + TOTAL;
        questionText.textContent = q.question;
        questionText.classList.remove('italic', 'font-normal', 'text-slate-400');
        questionText.classList.add('not-italic');

        const mixedOptions = shuffleArray([...q.options]);
        answersDiv.innerHTML = mixedOptions.map((opt, i) => `
            <button class="a-btn slide flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-200 dark:border-slate-700 rounded-2xl px-3 py-3 hover:border-brand hover:bg-brand-soft dark:hover:bg-blue-900/20 transition-all text-left" data-answer="${escapeHtml(opt)}" style="animation-delay:${i * 0.04}s">
                <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-brand/30 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-brand dark:text-blue-400">${LTR[i]}</div>
                <div class="a-txt text-[13px] font-semibold text-slate-700 dark:text-slate-200 leading-snug">${escapeHtml(opt)}</div>
            </button>
        `).join('');

        document.querySelectorAll('#answers .a-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!gameActive) return;
                const selected = btn.getAttribute('data-answer');
                const correct = selectedQuestions[currentIndex].reponse_correcte;
                handleAnswer(btn, selected, correct);
            });
        });
    }

    async function handleAnswer(btn, selected, correct) {
        if (!gameActive) return;
        document.querySelectorAll('#answers .a-btn').forEach(b => {
            b.disabled = true;
            b.classList.remove('hover:border-brand', 'hover:bg-brand-soft', 'dark:hover:bg-blue-900/20');
        });

        if (selected === correct) {
            btn.classList.remove('bg-slate-50', 'dark:bg-slate-800/60', 'border-slate-200', 'dark:border-slate-700');
            btn.classList.add('bg-emerald-50', 'dark:bg-emerald-900/20', 'border-emerald-500');
            btn.querySelector('.a-ltr').textContent = '✓';
            btn.querySelector('.a-ltr').classList.add('bg-emerald-500', 'text-white', 'border-emerald-500');
            setTimeout(() => {
                if (!gameActive) return;
                if (currentIndex + 1 < TOTAL) {
                    currentIndex++;
                    renderCurrentQuestion();
                } else {
                    winGame();
                }
            }, 380);
        } else {
            btn.classList.remove('bg-slate-50', 'dark:bg-slate-800/60', 'border-slate-200', 'dark:border-slate-700');
            btn.classList.add('bg-red-50', 'dark:bg-red-900/20', 'border-red-500');
            document.querySelectorAll('#answers .a-btn').forEach(b => {
                if (b.getAttribute('data-answer') === correct) {
                    b.classList.remove('bg-slate-50', 'dark:bg-slate-800/60', 'border-slate-200', 'dark:border-slate-700');
                    b.classList.add('bg-emerald-50', 'dark:bg-emerald-900/20', 'border-emerald-500');
                }
            });
            setTimeout(() => loseGame('Mauvaise réponse ! Vous perdez votre mise.'), 650);
        }
    }

    function startTimer() {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            if (!gameActive) return;
            if (timeLeft <= 0) {
                loseGame('Temps écoulé !');
                return;
            }
            timeLeft--;
            tFill.style.width = (timeLeft / TIME) * 100 + '%';
            if (timeLeft <= 15) {
                tFill.classList.remove('bg-brand');
                tFill.classList.add('bg-red-500');
            }
        }, 1000);
    }

    async function winGame() {
        if (!gameActive) return;
        clearInterval(timerInterval);
        gameActive = false;
        const result = await apiCall('win');
        if (result.success) {
            wallet = result.balance;
            updateWalletUI();
            progBar.style.width = '100%';
            resultDiv.classList.remove('hidden');
            resultDiv.classList.add('flex');
            rIcon.textContent = '🏆';
            rTitle.textContent = 'VICTOIRE !';
            rTitle.className = 'font-extrabold text-[26px] text-emerald-400';
            rGain.textContent = '+ ' + result.gain.toLocaleString('fr') + ' KMF';
            rReason.textContent = 'Bravo ! 8/8 correctes. Gains ajoutés à votre solde.';
        } else {
            loseGame('Erreur lors du crédit des gains');
        }
    }

    async function loseGame(reason) {
        if (!gameActive) return;
        clearInterval(timerInterval);
        gameActive = false;
        await apiCall('lose');
        const balRes = await fetch('?action=get_balance');
        const balData = await balRes.json();
        if (balData.balance !== undefined) wallet = balData.balance;
        updateWalletUI();
        resultDiv.classList.remove('hidden');
        resultDiv.classList.add('flex');
        rIcon.textContent = '💀';
        rTitle.textContent = 'GAME OVER';
        rTitle.className = 'font-extrabold text-[26px] text-red-400';
        rGain.textContent = '− ' + MISE_FIXE.toLocaleString('fr') + ' KMF';
        rReason.textContent = reason;
    }

    async function resetGame() {
        if (timerInterval) clearInterval(timerInterval);
        gameActive = false;
        resultDiv.classList.add('hidden');
        resultDiv.classList.remove('flex');
        btnLaunch.disabled = false;
        currentIndex = 0;
        timeLeft = TIME;
        sQ.textContent = '—';
        sM.textContent = '—';
        sG.textContent = '—';
        tFill.style.width = '100%';
        tFill.classList.remove('bg-red-500');
        tFill.classList.add('bg-brand');
        progBar.style.width = '0%';
        miseInfo.textContent = 'Prêt à jouer !';
        miseInfo.classList.remove('text-red-500');
        miseInfo.classList.add('text-emerald-500');
        questionBadge.textContent = 'Question ?';
        questionText.textContent = 'En attente de votre mise…';
        questionText.classList.add('italic', 'font-normal', 'text-slate-400');
        answersDiv.innerHTML = ['A', 'B', 'C', 'D'].map(l => `
            <button class="a-btn flex items-center gap-3 bg-slate-50 dark:bg-slate-800/60 border-2 border-slate-100 dark:border-slate-800 rounded-2xl px-3 py-3 opacity-60" disabled>
                <div class="a-ltr shrink-0 w-8 h-8 rounded-full bg-white dark:bg-slate-900 border-2 border-slate-200 dark:border-slate-700 flex items-center justify-center font-extrabold text-[13px] text-slate-400">${l}</div>
                <div class="a-txt text-[13px] font-semibold text-slate-500">—</div>
            </button>
        `).join('');
        const balRes = await fetch('?action=get_balance');
        const balData = await balRes.json();
        if (balData.balance !== undefined) wallet = balData.balance;
        updateWalletUI();
    }

    btnLaunch.addEventListener('click', startGame);
    document.getElementById('btn-replay').addEventListener('click', resetGame);
    loadQuestions();

    document.addEventListener('contextmenu', e => e.preventDefault());
    window.addEventListener('keydown', e => {
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I') || (e.ctrlKey && e.key === 'u')) e.preventDefault();
    });
</script>
</body>
</html>