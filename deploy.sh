#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/var/www/gallotta.fr/SymfonyCasino"

echo "▶ Déploiement depuis ${PROJECT_DIR}"

cd "$PROJECT_DIR"

# 1. Vérifier que le fichier d'env existe
if [ ! -f .env.prod.docker ]; then
  echo "❌ Fichier .env.prod.docker introuvable."
  echo "   Crée-le à la racine du projet avec APP_ENV, APP_SECRET, CADDY_MERCURE_JWT_SECRET."
  exit 1
fi

# 2. Charger les variables d'environnement
set -a
# shellcheck disable=SC1091
source .env.prod.docker
set +a

# 3. Vérifier que les variables critiques sont présentes
REQUIRED_VARS=(APP_ENV APP_SECRET CADDY_MERCURE_JWT_SECRET)

MISSING=0
for var in "${REQUIRED_VARS[@]}"; do
  if [ -z "${!var:-}" ]; then
    echo "❌ Variable requise manquante : $var"
    MISSING=1
  fi
done

if [ "$MISSING" -ne 0 ]; then
  echo "⛔ Arrêt du déploiement (variables manquantes)."
  exit 1
fi

echo "✅ Variables d'environnement OK."

# 4. Récupérer les dernières modifications git
echo "▶ git pull --ff-only"
git pull --ff-only

# 5. Build + up des containers avec les fichiers compose
echo "▶ docker compose -f compose.yaml -f compose.prod.yaml up -d --build"
docker compose -f compose.yaml -f compose.prod.yaml up -d --build

echo "✅ Déploiement terminé."

