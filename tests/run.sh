#!/usr/bin/env bash
# Arabic to Latin — local test runner.
#
# Builds a fresh WordPress (SQLite, throwaway), installs the integration
# plugins from a local download cache, starts a local web server, runs every
# suite in tests/suites and exits non-zero if anything fails.
#
#   tests/run.sh                      # all suites, latest WordPress
#   tests/run.sh --wp=6.4             # minimum supported WordPress
#   tests/run.sh --suite=titles,http  # some suites only
#   tests/run.sh --wpml=/path/to/sitepress-multilingual-cms   # + WPML suites (Docker MySQL)
#   tests/run.sh --keep               # keep the site and server for debugging
#   tests/run.sh --php=7.4            # run everything inside Docker on another PHP version
#
# Needs: php, wp (WP-CLI), curl, unzip, python3. WPML mode also needs docker.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TESTS="$ROOT/tests"
PLUGIN="$ROOT/arabic-to-lat"
CACHE="$TESTS/.cache"
WP_VERSION="latest"
ONLY=""
WPML_PATH=""
PHP_DOCKER=""
KEEP=0
PORT="${ATL_TEST_PORT:-8890}"

for arg in "$@"; do
	case "$arg" in
		--wp=*) WP_VERSION="${arg#*=}" ;;
		--suite=*) ONLY="${arg#*=}" ;;
		--wpml=*) WPML_PATH="${arg#*=}" ;;
		--keep) KEEP=1 ;;
		--php=*) PHP_DOCKER="${arg#*=}" ;;
		-h|--help) sed -n '2,17p' "$0"; exit 0 ;;
		*) echo "Unknown option: $arg" >&2; exit 2 ;;
	esac
done

# Another PHP version: rerun this script inside a container built from tests/docker.
if [ -n "$PHP_DOCKER" ]; then
	command -v docker > /dev/null && docker info > /dev/null 2>&1 || { echo "--php needs Docker running" >&2; exit 2; }
	[ -n "$WPML_PATH" ] && echo "(--wpml is not supported together with --php; skipping WPML)" >&2
	image="atl-tests:php$PHP_DOCKER"
	docker build -q -t "$image" --build-arg PHP_VERSION="$PHP_DOCKER" "$TESTS/docker" > /dev/null || exit 2
	args=()
	for arg in "$@"; do case "$arg" in --php=*|--wpml=*) ;; *) args+=("$arg") ;; esac; done
	exec docker run --rm -e ATL_SITE_SUFFIX="-php$PHP_DOCKER" -v "$ROOT:$ROOT" -w "$ROOT" "$image" bash tests/run.sh "${args[@]+"${args[@]}"}"
fi

for bin in php wp curl unzip python3; do
	command -v "$bin" >/dev/null || { echo "Missing: $bin" >&2; exit 2; }
done

SITE="$TESTS/.site-$WP_VERSION${ATL_SITE_SUFFIX:-}"
RESULTS="$TESTS/.results${ATL_SITE_SUFFIX:-}.jsonl"
LOG="$TESTS/.server${ATL_SITE_SUFFIX:-}.log"
mkdir -p "$CACHE"
: > "$RESULTS"

wpq() { wp --path="$SITE" "$@" 2> >(grep -viE 'deprecat|^$' >&2); }

# Plugins used by suites, downloaded once from wordpress.org.
fetch_plugin() {
	local slug="$1" version="${2:-}"
	local zip="$CACHE/$slug${version:+.$version}.zip"
	if [ ! -s "$zip" ]; then
		echo "  downloading $slug${version:+ $version}"
		curl -fsSL -o "$zip" "https://downloads.wordpress.org/plugin/$slug${version:+.$version}.zip" || { rm -f "$zip"; return 1; }
	fi
	unzip -qo "$zip" -d "$SITE/wp-content/plugins/"
}

build_site() {
	echo "▸ Building WordPress $WP_VERSION on PHP $(php -r 'echo PHP_VERSION;') in $SITE"
	rm -rf "$SITE"
	mkdir -p "$SITE"
	wpq core download --version="$WP_VERSION" --quiet || exit 2
	fetch_plugin sqlite-database-integration || exit 2
	cp "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" "$SITE/wp-content/db.php"
	sed -i.bak "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$SITE/wp-content/db.php" && rm -f "$SITE/wp-content/db.php.bak"
	wpq config create --dbname=x --dbuser=x --dbpass=x --skip-check --quiet --force
	wpq core install --url="http://localhost:$PORT" --title="ATL tests" --admin_user=admin \
		--admin_email=admin@example.test --admin_password="$(openssl rand -hex 16)" --skip-email --quiet || exit 2
	wpq rewrite structure '/%postname%/' --quiet
	ln -sfn "$PLUGIN" "$SITE/wp-content/plugins/arabic-to-lat"
	wpq plugin activate arabic-to-lat --quiet || exit 2
	for slug in woocommerce polylang translatepress-multilingual duplicate-post; do
		fetch_plugin "$slug" || echo "  (could not download $slug; suites needing it will be skipped)"
	done
	mkdir -p "$SITE/wp-content/mu-plugins"
	cp "$TESTS/lib/mu-probe.php" "$SITE/wp-content/mu-plugins/atl-test-probe.php"
}

start_server() {
	( cd "$SITE" && exec wp server --host=localhost --port="$PORT" ) > "$LOG" 2>&1 < /dev/null &
	SERVER_PID=$!
	for _ in $(seq 1 30); do
		curl -s -o /dev/null "http://localhost:$PORT/" && return 0
		sleep 0.5
	done
	echo "Server did not start (see $LOG)" >&2
	exit 2
}

stop_server() {
	[ -n "${SERVER_PID:-}" ] && pkill -P "$SERVER_PID" 2>/dev/null; kill "${SERVER_PID:-}" 2>/dev/null
	pkill -f "localhost:$PORT" 2>/dev/null
	true
}

# Fresh content and default plugin state before each suite.
reset_site() {
	wpq site empty --yes --quiet
	wpq option delete arabic_to_lat_settings polylang --quiet 2>/dev/null
	wpq eval 'global $wpdb; if ( $wpdb->get_var( "SHOW TABLES LIKE \"{$wpdb->prefix}woocommerce_attribute_taxonomies\"" ) ) { $wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_attribute_taxonomies" ); } delete_transient( "wc_attribute_taxonomies" ); delete_transient( "arabic_to_lat_result_1" );' 2>/dev/null
	wpq rewrite structure '/%postname%/' --quiet
}

run_suite() {
	local file="$1" name requires plugins
	name="$(basename "$file" .php)"
	if [ -n "$ONLY" ] && [[ ",$ONLY," != *",$name,"* ]]; then return; fi
	requires="$(sed -n 's#^// requires: ##p' "$file" | head -1)"
	plugins=""
	for p in ${requires//,/ }; do
		if [ "$p" = "wpml" ]; then return; fi
		if [ ! -d "$SITE/wp-content/plugins/$p" ]; then
			echo "▸ $name: SKIPPED (needs $p)"
			printf '{"suite":"%s","id":"-","label":"skipped: needs %s","ok":true,"kind":"skip","detail":""}\n' "$name" "$p" >> "$RESULTS"
			return
		fi
		plugins="$plugins $p"
	done
	echo "▸ $name${plugins:+ (with$plugins)}"
	reset_site
	# shellcheck disable=SC2086
	[ -n "$plugins" ] && wpq plugin activate $plugins --quiet > /dev/null 2>&1
	for p in $plugins; do
		if ! wp --path="$SITE" plugin is-active "$p" 2>/dev/null; then
			echo "    – skipped: $p does not activate on WordPress $(wp --path="$SITE" core version 2>/dev/null) (its own requirements)"
			printf '{"suite":"%s","id":"-","label":"skipped: %s does not activate on this WordPress version","ok":true,"kind":"skip","detail":""}\n' "$name" "$p" >> "$RESULTS"
			# shellcheck disable=SC2086
			wpq plugin deactivate $plugins --quiet > /dev/null 2>&1
			return
		fi
	done
	wpq rewrite flush --quiet
	local out status
	out="$(ATL_TEST_SUITE="$name" ATL_TEST_RESULTS="$RESULTS" ATL_TEST_URL="http://localhost:$PORT" ATL_TEST_PATH="$SITE" \
		wp --path="$SITE" eval-file "$file" 2>&1)"
	status=$?
	echo "$out" | grep -E '^(PASS|FAIL|NOTE) ' | sed 's/^/    /'
	if [ $status -ne 0 ] || echo "$out" | grep -qE 'PHP (Fatal|Parse) error|Uncaught'; then
		echo "$out" | grep -E 'Fatal|Uncaught|error' | head -5 | sed 's/^/    !! /'
		printf '{"suite":"%s","id":"-","label":"suite crashed","ok":false,"kind":"check","detail":"exit %s"}\n' "$name" "$status" >> "$RESULTS"
	fi
	# shellcheck disable=SC2086
	[ -n "$plugins" ] && wpq plugin deactivate $plugins --quiet > /dev/null 2>&1
}

trap '[ $KEEP -eq 0 ] && stop_server' EXIT

build_site
start_server
for suite in "$TESTS"/suites/*.php; do
	run_suite "$suite"
done

if [ -n "$WPML_PATH" ]; then
	"$TESTS/run-wpml.sh" "$WPML_PATH" "$RESULTS" "$ONLY" || true
fi

python3 - "$RESULTS" <<'PY'
import json, sys, collections
rows = [json.loads(l) for l in open(sys.argv[1], encoding='utf-8') if l.strip()]
checks = [r for r in rows if r['kind'] == 'check']
failed = [r for r in checks if not r['ok']]
known = [r for r in rows if r['kind'] == 'known']
skipped = [r for r in rows if r['kind'] == 'skip']
by_suite = collections.OrderedDict()
for r in checks:
    s = by_suite.setdefault(r['suite'], [0, 0])
    s[0 if r['ok'] else 1] += 1
print('\n══ Summary ══')
for suite, (ok, bad) in by_suite.items():
    print(f"  {'✗' if bad else '✓'} {suite:<16} {ok} passed{'' if not bad else f', {bad} FAILED'}")
for r in skipped:
    print(f"  – {r['suite']:<16} {r['label']}")
if known:
    print('\nKnown limitations (documented, not failures):')
    for r in known:
        print(f"  {r['id']:<4} {r['label']} — {r['detail']}")
if failed:
    print('\nFAILURES:')
    for r in failed:
        print(f"  {r['suite']}/{r['id']:<4} {r['label']} — {r['detail']}")
ids = sorted({r['id'] for r in checks if r['id'] != '-'}, key=lambda x: (x[0], int(''.join(c for c in x[1:] if c.isdigit()) or 0)))
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed · scenarios covered: {len(ids)} ({', '.join(ids)})")
sys.exit(1 if failed or not checks else 0)
PY
status=$?
[ $KEEP -eq 1 ] && echo "Kept: site $SITE, server http://localhost:$PORT (pid ${SERVER_PID:-?})"
exit $status
