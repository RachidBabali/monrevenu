#!/bin/bash
# tools/composer-audit.sh : contrôle des vulnérabilités des dépendances, à lancer en local
# (Composer n'est pas disponible sur l'hébergement). Écrit un fichier daté à commiter, puis à
# déposer sur le serveur : storage/audit/composer-audit.json, lu par la page Audit, onglet Dépendances.
set -u
racine="$(cd "$(dirname "$0")/.." && pwd)"
sortie="$racine/storage/audit/composer-audit.json"
mkdir -p "$(dirname "$sortie")"

if ! command -v composer > /dev/null 2>&1; then
  echo "composer introuvable : installez-le puis relancez." >&2
  exit 1
fi

cd "$racine" || exit 1
brut="$(composer audit --format=json --no-interaction 2>/dev/null)"
code=$?
if [ -z "$brut" ]; then
  echo "composer audit n'a rien renvoyé (code $code)." >&2
  exit 1
fi

php -r '
$brut = file_get_contents("php://stdin");
$donnees = json_decode($brut, true) ?: [];
$avis = [];
foreach ($donnees["advisories"] ?? [] as $paquet => $liste) {
  foreach ($liste as $a) {
    $avis[] = ["paquet" => $paquet, "titre" => $a["title"] ?? "", "cve" => $a["cve"] ?? "", "gravite" => $a["severity"] ?? ""];
  }
}
echo json_encode(["date" => date("Y-m-d H:i:s"), "advisories" => $avis, "abandonnes" => array_keys($donnees["abandoned"] ?? [])],
  JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
' <<< "$brut" > "$sortie"

echo "Écrit : $sortie"
php -r '$d = json_decode(file_get_contents($argv[1]), true); echo count($d["advisories"]) . " vulnérabilité(s) signalée(s).\n";' "$sortie"
