#!/usr/bin/env bash
# WPML suites (called by run.sh --wpml=PATH). WPML needs MySQL, so this starts
# a throwaway MySQL 8 container, builds a separate site, runs tests/suites-wpml
# and removes the container afterwards.
#
#   tests/run-wpml.sh /path/to/sitepress-multilingual-cms [results.jsonl] [suite,list]
set -uo pipefail

WPML_SRC="${1:?path to sitepress-multilingual-cms}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TESTS="$ROOT/tests"
RESULTS="${2:-$TESTS/.results-wpml.jsonl}"
ONLY="${3:-}"
SITE="$TESTS/.site-wpml"
PORT="${ATL_TEST_WPML_PORT:-8891}"
DBPORT="${ATL_TEST_DB_PORT:-33062}"
NAME="atl-test-mysql-$$"

[ -f "$WPML_SRC/sitepress.php" ] || { echo "Not a WPML folder: $WPML_SRC" >&2; exit 2; }
docker info > /dev/null 2>&1 || { echo "▸ wpml: SKIPPED (Docker is not running)"; exit 0; }
docker image inspect mysql:8.0 > /dev/null 2>&1 || docker pull -q mysql:8.0 > /dev/null || exit 2

PW="$(openssl rand -hex 12)"
cleanup() {
	pkill -f "localhost:$PORT" 2>/dev/null
	docker stop "$NAME" > /dev/null 2>&1
}
trap cleanup EXIT

echo "▸ wpml: starting MySQL"
docker run -d --rm --name "$NAME" -e MYSQL_ROOT_PASSWORD="$PW" -e MYSQL_DATABASE=atl -p "127.0.0.1:$DBPORT:3306" mysql:8.0 > /dev/null || exit 2
for _ in $(seq 1 60); do docker exec "$NAME" mysqladmin ping -uroot -p"$PW" --silent > /dev/null 2>&1 && break; sleep 2; done
sleep 5

wpq() { wp --path="$SITE" "$@" 2> >(grep -viE 'deprecat|warning|^$' >&2); }
build() {
	wpq db reset --yes --quiet > /dev/null 2>&1
	wpq core install --url="http://localhost:$PORT" --title="ATL WPML" --admin_user=admin --admin_email=admin@example.test --admin_password="$(openssl rand -hex 16)" --skip-email --quiet || return 1
	wpq rewrite structure '/%postname%/' --quiet
	wpq plugin activate arabic-to-lat sitepress-multilingual-cms --quiet > /dev/null 2>&1
	wpq eval-file "$TESTS/lib/wpml-setup.php" > /dev/null 2>&1
	wpq rewrite flush --quiet
}

echo "▸ wpml: building site"
rm -rf "$SITE" && mkdir -p "$SITE"
wpq core download --quiet || exit 2
wpq config create --dbname=atl --dbuser=root --dbpass="$PW" --dbhost="127.0.0.1:$DBPORT" --skip-check --quiet --force || exit 2
ln -sfn "$ROOT/arabic-to-lat" "$SITE/wp-content/plugins/arabic-to-lat"
cp -R "$WPML_SRC" "$SITE/wp-content/plugins/sitepress-multilingual-cms"
build || exit 2
( cd "$SITE" && exec wp server --host=localhost --port="$PORT" ) > "$TESTS/.server-wpml.log" 2>&1 < /dev/null &
for _ in $(seq 1 30); do curl -s -o /dev/null "http://localhost:$PORT/" && break; sleep 0.5; done

for file in "$TESTS"/suites-wpml/*.php; do
	name="$(basename "$file" .php)"
	if [ -n "$ONLY" ] && [[ ",$ONLY," != *",$name,"* ]] && [[ ",$ONLY," != *",wpml,"* ]]; then continue; fi
	echo "▸ $name (WPML, MySQL)"
	build
	out="$(ATL_TEST_SUITE="$name" ATL_TEST_RESULTS="$RESULTS" ATL_TEST_URL="http://localhost:$PORT" wp --path="$SITE" eval-file "$file" 2>&1)"
	status=$?
	echo "$out" | grep -E '^(PASS|FAIL|NOTE) ' | sed 's/^/    /'
	if [ $status -ne 0 ] || echo "$out" | grep -qE 'PHP (Fatal|Parse) error|Uncaught'; then
		echo "$out" | grep -E 'Fatal|Uncaught' | head -3 | sed 's/^/    !! /'
		printf '{"suite":"%s","id":"-","label":"suite crashed","ok":false,"kind":"check","detail":"exit %s"}\n' "$name" "$status" >> "$RESULTS"
	fi
done
