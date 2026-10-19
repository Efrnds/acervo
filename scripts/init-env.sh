#!/bin/sh
set -eu
cd "$(dirname "$0")/.."

if [ -f .env ]; then
  echo ".env já existe. Apague o arquivo se quiser gerar outro."
  exit 0
fi

url="${1:-http://localhost:8090}"
rand() { openssl rand -base64 24 | tr -d '/+=' | head -c 20; }

db_password="$(rand)"
db_root="$(rand)"
admin_password="$(rand)"

cat > .env << EOF
DB_NAME=acervo
DB_USER=acervo
DB_PASSWORD=${db_password}
DB_ROOT_PASSWORD=${db_root}
WP_URL=${url}
WP_TITLE=Acervo de prática
WP_ADMIN_USER=luiza
WP_ADMIN_PASSWORD=${admin_password}
WP_ADMIN_EMAIL=luiza@example.com
EOF
chmod 600 .env

echo "WP_URL=${url}"
echo "usuário: luiza"
echo "senha: ${admin_password}"
echo "Guarda essa senha. Ela não fica no git."
