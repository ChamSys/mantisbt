<?php
# Development configuration for the fork's docker stack (chamsys/stack/).
# Mounted over config/config_inc.php; values come from the environment set in
# docker-compose.yml. Not for production.

$g_db_type = 'mysqli';
$g_hostname = getenv( 'MANTIS_DB_HOST' );
$g_database_name = getenv( 'MANTIS_DB_NAME' );
$g_db_username = getenv( 'MANTIS_DB_USER' );
$g_db_password = getenv( 'MANTIS_DB_PASSWORD' );

$g_path = getenv( 'MANTIS_URL' );
$g_default_timezone = 'Europe/London';
$g_crypto_master_salt = 'dev-only-salt-not-secret-0123456789abcdef';

$g_show_detailed_errors = ON;
$g_display_errors = array(
	E_USER_ERROR => DISPLAY_ERROR_HALT,
	E_ALL => DISPLAY_ERROR_INLINE,
);

# No mail server in the dev stack.
$g_enable_email_notification = OFF;

# Search: Meilisearch is part of the stack. Set MANTIS_SEARCH_BACKEND=database to use the SQL
# search instead. The index is kept up to date by scripts/search_index.php (`stack.sh search`).
$g_search_backend = getenv( 'MANTIS_SEARCH_BACKEND' );
$g_search_meilisearch_url = getenv( 'MEILI_URL' );
$g_search_meilisearch_key = getenv( 'MEILI_MASTER_KEY' );
