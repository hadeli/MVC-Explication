#!/usr/bin/env bash
# Vérifie qu'une étape se comporte comme attendu : inscription, connexion, accueil connecté, déconnexion.
# Fonctionne sur le code que l'élève écrit dans etapes/NN-nom/, comme sur la correction complète.
# Usage : ./verifier.sh 03      (numéro de l'étape, 1 à 14 ; « 3 » et « 03 » sont acceptés)
set -u
NUM="${1:?numero de l etape requis, ex: ./verifier.sh 04}"
case "$NUM" in [1-9]) NUM="0$NUM" ;; esac
DOSSIER=$(ls -d "$(dirname "$0")"/etapes/"$NUM"-* 2>/dev/null | head -1)
[ -d "$DOSSIER" ] || { echo "Étape $NUM introuvable"; exit 1; }
DOSSIER=$(cd "$DOSSIER" && pwd)

# Dans le dépôt des élèves, les dossiers 02 à 14 ne contiennent que le README : le code est à écrire par l'élève.
if ! ls "$DOSSIER"/*.php >/dev/null 2>&1 && ! ls "$DOSSIER"/public/*.php >/dev/null 2>&1; then
    echo "Étape $NUM : aucun fichier PHP dans $DOSSIER."
    echo "  Écrivez le code de l'étape dans ce dossier (voir son README.md et PLAN.md), puis relancez."
    exit 1
fi
PORT=${PORT:-8765}
BASE="http://127.0.0.1:$PORT"
TMP=$(mktemp -d)

# .env absent ? on part de l'exemple
[ -f "$DOSSIER/.env.example" ] && [ ! -f "$DOSSIER/.env" ] && cp "$DOSSIER/.env.example" "$DOSSIER/.env"
rm -f "$DOSSIER/database.sqlite" "$DOSSIER/public/database.sqlite"
rm -rf "$DOSSIER/cache"

# Racine web et URLs selon l'étape
if [ -d "$DOSSIER/public" ]; then
    DOCROOT="$DOSSIER/public"; P_INDEX="/"; P_LOGIN="/login"; P_REGISTER="/register"; P_LOGOUT="/logout"
else
    DOCROOT="$DOSSIER"; P_INDEX="/index.php"; P_LOGIN="/login.php"; P_REGISTER="/register.php"; P_LOGOUT="/index.php?action=logout"
fi

# Un serveur déjà présent sur le port (lancement précédent interrompu ?) fausserait tous les tests.
if curl -s -o /dev/null "$BASE/"; then
    echo "Le port $PORT est déjà utilisé : arrêtez le serveur qui l'occupe, ou relancez avec PORT=8766 ./verifier.sh $NUM"
    rm -rf "$TMP"; exit 1
fi

php -S 127.0.0.1:$PORT -t "$DOCROOT" >"$TMP/server.log" 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null; rm -rf "$TMP"' EXIT
for _ in 1 2 3 4 5 6 7 8 9 10; do curl -s -o /dev/null "$BASE$P_INDEX" && break; sleep 0.2; done
if ! kill -0 $PID 2>/dev/null; then
    echo "Le serveur PHP n'a pas démarré :"; sed 's/^/  /' "$TMP/server.log"; exit 1
fi

ECHECS=0
verifier() { # libellé, code HTTP attendu, code obtenu
    if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  ECHEC $1 (code HTTP attendu : $2, obtenu : $3)"; ECHECS=$((ECHECS+1)); fi
}
# Les textes cherchés sont ceux des pages de l'étape 1 : ils doivent rester identiques à toutes les étapes.
contient() { # libellé, page reçue, texte attendu
    if grep -q -- "$3" <<<"$2"; then echo "  ok   $1"; else echo "  ECHEC $1 (la page devrait contenir « $3 »)"; ECHECS=$((ECHECS+1)); fi
}
ne_contient_pas() { # libellé, page reçue, texte interdit
    if grep -q -- "$3" <<<"$2"; then echo "  ECHEC $1 (la page ne devrait pas contenir « $3 » tel quel)"; ECHECS=$((ECHECS+1)); else echo "  ok   $1"; fi
}

echo "Étape $NUM : $(basename "$DOSSIER")"

R=$(curl -s "$BASE$P_INDEX")
contient "accueil non connecté" "$R" "pas connecté"

R=$(curl -s -d 'email=test@example.com&password=abc&confirmation=abc' "$BASE$P_REGISTER")
contient "inscription : mot de passe trop court refusé" "$R" "8 caractères"

R=$(curl -s -d 'email=test@example.com&password=motdepasse1&confirmation=motdepasse1' "$BASE$P_REGISTER")
contient "inscription : succès" "$R" "Votre compte a été créé"

R=$(curl -s -d 'email=test@example.com&password=motdepasse1&confirmation=motdepasse1' "$BASE$P_REGISTER")
contient "inscription : doublon refusé" "$R" "existe déjà"

R=$(curl -s -d 'email=test@example.com&password=faux' "$BASE$P_LOGIN")
contient "connexion : mauvais mot de passe refusé" "$R" "incorrect"

CODE=$(curl -s -c "$TMP/cookies" -o /dev/null -w '%{http_code}' -d 'email=test@example.com&password=motdepasse1' "$BASE$P_LOGIN")
verifier "connexion : redirection 302" 302 "$CODE"

R=$(curl -s -b "$TMP/cookies" "$BASE$P_INDEX")
contient "accueil connecté affiche l'email" "$R" "Bonjour <strong>test@example.com</strong>"

CODE=$(curl -s -b "$TMP/cookies" -c "$TMP/cookies" -o /dev/null -w '%{http_code}' "$BASE$P_LOGOUT")
verifier "déconnexion : redirection 302" 302 "$CODE"

R=$(curl -s -b "$TMP/cookies" "$BASE$P_INDEX")
contient "accueil après déconnexion" "$R" "pas connecté"

if [ -d "$DOSSIER/public" ]; then
    # php -S se place dans le docroot : un DB_DSN relatif résolu depuis le dossier courant crée la base dans public/,
    # où elle redevient téléchargeable. La piste de l'étape 3 demande de la résoudre depuis la racine du projet.
    if [ -f "$DOCROOT/database.sqlite" ]; then
        echo "  ECHEC base SQLite créée dans public/ : DB_DSN relatif résolu depuis le dossier courant du serveur au lieu de la racine du projet (piste de l'étape 3)"
        ECHECS=$((ECHECS+1))
    fi
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/inexistant")
    verifier "page inconnue : 404" 404 "$CODE"
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/database.sqlite")
    verifier "base de données inaccessible par URL" 404 "$CODE"
    CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/.env")
    verifier "fichier .env inaccessible par URL" 404 "$CODE"
    R=$(curl -s "$BASE$P_LOGIN/")
    contient "slash final toléré (/login/)" "$R" "Se connecter"
fi

# Échappement HTML : l'email piégé ne doit jamais sortir brut
R=$(curl -s -d 'email=%3Cb%3Ex%3C%2Fb%3E%40test.fr&password=faux' "$BASE$P_LOGIN")
ne_contient_pas "échappement de la saisie utilisateur" "$R" '<b>x</b>'

if grep -qiE 'warning|error|fatal' "$TMP/server.log"; then echo "  ECHEC erreurs PHP dans le journal :"; grep -iE 'warning|error|fatal' "$TMP/server.log" | head -5; ECHECS=$((ECHECS+1)); fi

rm -f "$DOSSIER/database.sqlite" "$DOSSIER/public/database.sqlite"
[ $ECHECS -eq 0 ] && echo "  => OK" || { echo "  => $ECHECS échec(s)"; exit 1; }
