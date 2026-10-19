#!/bin/sh
set -eu
cd /var/www/html

echo "Esperando os arquivos do WordPress."
i=0
while [ ! -f wp-includes/version.php ]; do
  i=$((i + 1))
  if [ "$i" -gt 60 ]; then
    echo "O volume do WordPress não ficou pronto." >&2
    exit 1
  fi
  sleep 2
done

echo "Esperando o MariaDB."
i=0
until wp db check >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -gt 40 ]; then
    echo "O banco não respondeu." >&2
    exit 1
  fi
  sleep 3
done

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

wp option update home "$WP_URL"
wp option update siteurl "$WP_URL"
wp language core install pt_BR --activate
wp site switch-language pt_BR
wp option update timezone_string America/Sao_Paulo
wp option update blog_public 0
wp rewrite structure '/%postname%/' --hard

if ! wp plugin is-installed tainacan; then
  wp plugin install tainacan --activate
else
  wp plugin activate tainacan
fi

if ! wp theme is-installed tainacan-interface; then
  wp theme install tainacan-interface --activate
else
  wp theme activate tainacan-interface
fi

wp language plugin install tainacan pt_BR || true
wp language theme install tainacan-interface pt_BR || true

wp eval-file /seed/bootstrap.php --user="$WP_ADMIN_USER"

if ! wp option get acervo_csv_imported >/dev/null 2>&1; then
  wp media import /seed/mais-gravuras.csv \
    --title="Planilha de gravuras para importar" \
    --user="$WP_ADMIN_USER"
  wp option update acervo_csv_imported 1
fi

wp rewrite flush --hard
wp config set WP_CACHE true --raw
wp config set DISABLE_WP_CRON true --raw
wp config set WP_POST_REVISIONS 5 --raw
hello_id="$(wp post list --post_type=post --name=hello-world --field=ID --format=ids)"
if [ -n "$hello_id" ]; then
  wp post delete $hello_id --force
fi
echo "Pronto: ${WP_URL}"
echo "Usuário: ${WP_ADMIN_USER}"
echo "Painel: ${WP_URL}/wp-admin"
