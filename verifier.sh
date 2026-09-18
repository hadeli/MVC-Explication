#!/usr/bin/env bash
# Vérifie qu'une étape se comporte comme attendu : inscription, connexion, accueil connecté, déconnexion.
# Usage : ./verifier.sh 03      (numéro de l'étape, 01 à 10)
set -u
NUM="${1:?numero de l etape requis, ex: ./verifier.sh 04}"
DOSSIER=$(ls -d "$(dirname "$0")"/etapes/"$NUM"-* 2>/dev/null | head -1)
[ -d "$DOSSIER" ] || { echo "Étape $NUM introuvable"; exit 1; }
DOSSIER=$(cd "$DOSSIER" && pwd)
PORT=${PORT:-8765}
BASE="http://127.0.0.1:$PORT"
TMP=$(mktemp -d)

# .env absent ? on part de l'exemple
[ -f "$DOSSIER/.env.example" ] && [ ! -f "$DOSSIER/.env" ] && cp "$DOSSIER/.env.example" "$DOSSIER/.env"
rm -f "$DOSSIER/database.sqlite"
rm -rf "$DOSSIER/cache"

# Racine web et URLs selon l'étape
if [ -d "$DOSSIER/public" ]; then
    DOCROOT="$DOSSIER/public"; P_INDEX="/"; P_LOGIN="/login"; P_REGISTER="/register"; P_LOGOUT="/logout"
else
    DOCROOT="$DOSSIER"; P_INDEX="/index.php"; P_LOGIN="/login.php"; P_REGISTER="/register.php"; P_LOGOUT="/index.php?action=logout"
fi

php -S 127.0.0.1:$PORT -t "$DOCROOT" >"$TMP/server.log" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null; rm -rf "$TMP"' EXIT
for _ in 1 2 3 4 5 6 7 8 9 10; do curl -s -o /dev/null "$BASE$P_INDEX" && break; sleep 0.2; done

ECHECS=0
verifier() { # libellé, attendu, obtenu
    if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  ECHEC $1 (attendu: $2 | obtenu: $3)"; ECHECS=$((ECHECS+1)); fi
}
contient() { grep -q -- "$2" <<<"$1" && echo oui || echo non; }

echo "Étape $NUM : $(basename "$DOSSIER")"

R=$(curl -s "$BASE$P_INDEX")
verifier "accueil non connecté" oui "$(contient "$R" "pas connecté")"

R=$(curl -s -d 'email=test@example.com&password=abc&confirmation=abc' "$BASE$P_REGISTER")
verifier "inscription : mot de passe trop court refusé" oui "$(contient "$R" "8 caractères")"

R=$(curl -s -d 'email=test@example.com&password=motdepasse1&confirmation=motdepasse1' "$BASE$P_REGISTER")
verifier "inscription : succès" oui "$(contient "$R" "Votre compte a été créé")"

R=$(curl -s -d 'email=test@example.com&password=motdepasse1&confirmation=motdepasse1' "$BASE$P_REGISTER")
verifier "inscription : doublon refusé" oui "$(contient "$R" "existe déjà")"

R=$(curl -s -d 'email=test@example.com&password=faux' "$BASE$P_LOGIN")
verifier "connexion : mauvais mot de passe refusé" oui "$(contient "$R" "incorrect")"

CODE=$(curl -s -c "$TMP/cookies" -o /dev/null -w '%{http_code}' -d 'email=test@example.com&password=motdepasse1' "$BASE$P_LOGIN")
verifier "connexion : redirection 302" 302 "$CODE"

R=$(curl -s -b "$TMP/cookies" "$BASE$P_INDEX")
verifier "accueil connecté affiche l'email" oui "$(contient "$R" "Bonjour <strong>test@example.com</strong>")"

CODE=$(curl -s -b "$TMP/cookies" -c "$TMP/cookies" -o /dev/null -w '%{http_code}' "$BASE$P_LOGOUT")
verifier "déconnexion : redirection 302" 302 "$CODE"

R=$(curl -s -b "$TMP/cookies" "$BASE$P_INDEX")
verifier "accueil après déconnexion" oui "$(contient "$R" "pas connecté")"

if [ -d "$DOSSIER/public" ]; then
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/inexistant")
    verifier "page inconnue : 404" 404 "$CODE"
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/../database.sqlite")
    verifier "base de données inaccessible par URL" 404 "$CODE"
fi

# Échappement HTML : l'email piégé ne doit jamais sortir brut
R=$(curl -s -d 'email=%3Cb%3Ex%3C%2Fb%3E%40test.fr&password=faux' "$BASE$P_LOGIN")
verifier "échappement de la saisie utilisateur" non "$(contient "$R" '<b>x</b>')"

if grep -qi 'warning\|error\|fatal' "$TMP/server.log"; then echo "  ECHEC erreurs PHP dans le journal :"; grep -i 'warning\|error\|fatal' "$TMP/server.log" | head -5; ECHECS=$((ECHECS+1)); fi

rm -f "$DOSSIER/database.sqlite"
[ $ECHECS -eq 0 ] && echo "  => OK" || { echo "  => $ECHECS échec(s)"; exit 1; }
