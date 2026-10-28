#!/bin/sh
# Helper for the development stack. Run from anywhere: stack.sh <command>
#
#   up         build the images and start the containers
#   down       stop the containers (data is kept)
#   reset      stop the containers and delete the database and search index
#   install    composer install, then create (or upgrade) the database schema
#   logs       follow the logs
#   php ARGS   run php in the php container, e.g. `stack.sh php -v`
#   sh         shell in the php container
#   sql        MariaDB client on the mantis database
#   search ARGS  scripts/search_index.php, e.g. `stack.sh search --reindex`
set -eu
cd "$(dirname "$0")"

compose() { docker compose "$@"; }
in_php() { compose exec -T php "$@"; }

case "${1:-}" in
	up)      compose up -d --build --wait ;;
	down)    compose down ;;
	reset)   compose down -v ;;
	install)
		compose exec php composer install --no-dev --no-interaction --prefer-dist
		installed=$(compose exec -T db mariadb -N -umantis -pmantis mantis -e \
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'mantis' AND table_name = 'mantis_config_table'")
		if [ "$installed" = "1" ]; then
			in_php php admin/upgrade_unattended.php
		else
			# upgrade_unattended.php cannot create a database from scratch, so run the web installer.
			port=${MANTIS_HTTP_PORT:-8088}
			curl -fsS "http://localhost:$port/admin/install.php" -d install=3 -d hostname=db \
				-d db_type=mysqli -d database_name=mantis -d db_username=mantis -d db_password=mantis \
				-d admin_username=root -d admin_password=root -d timezone=Europe/London -d db_exists=1 \
				| sed -e 's/<[^>]*>/ /g' | grep -E 'Installation Complete|BAD' || true
			echo "Log in at http://localhost:$port/ as administrator / root"
		fi
		;;
	logs)    compose logs -f ;;
	php)     shift; in_php php "$@" ;;
	sh)      compose exec php bash ;;
	sql)     compose exec db mariadb -umantis -pmantis mantis ;;
	search)  shift; in_php php scripts/search_index.php "$@" ;;
	*)       sed -n '2,13p' "$0"; exit 1 ;;
esac
