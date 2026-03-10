#!/usr/bin/env bash
#
# Tests de fumée du site et de l'API.
#
# Prérequis : les deux serveurs tournent sur 50180 et 50181.
#   php -S 127.0.0.1:50180 -t src/site tools/router.php &
#   php -S 127.0.0.1:50181 -t src/webservice tools/router.php &
#
set -euo pipefail

SITE="${SITE:-http://127.0.0.1:50180}"
API="${API:-http://127.0.0.1:50181}"
JAR="$(mktemp)"
SSE_HEADERS="$(mktemp)"
SSE_BODY="$(mktemp)"
trap 'rm -f "$JAR" "$SSE_HEADERS" "$SSE_BODY"' EXIT

pass=0

status() {
    curl -s -o /dev/null -w '%{http_code}' "$@"
}

expect_status() {
    local expected="$1"
    shift
    local got
    got="$(status "$@")"
    if [ "$got" != "$expected" ]; then
        echo "ÉCHEC : attendu $expected, obtenu $got — $*" >&2
        exit 1
    fi
    pass=$((pass + 1))
}

json_get() {
    local key="$1"
    php -r '
        $data = json_decode(stream_get_contents(STDIN), true);
        foreach (explode(".", $argv[1]) as $part) {
            $data = $data[$part] ?? null;
        }
        echo is_bool($data) ? ($data ? "true" : "false") : $data;
    ' "$key"
}

echo "== Site =="
expect_status 200 "$SITE/login"
expect_status 401 "$SITE/dashboard"

# Mauvais mot de passe : PRG vers /login.
expect_status 303 -c "$JAR" -b "$JAR" -d 'username=bean&password=incorrect' "$SITE/login"
ERROR_PAGE="$(mktemp)"
curl -s -c "$JAR" -b "$JAR" "$SITE/login" > "$ERROR_PAGE"
grep -q 'Identifiant ou mot de passe incorrect' "$ERROR_PAGE"
rm -f "$ERROR_PAGE"
pass=$((pass + 1))

# Connexion valide puis tableau de bord.
expect_status 303 -c "$JAR" -b "$JAR" -d 'username=bean&password=Beer4Life' "$SITE/login"
DASHBOARD="$(mktemp)"
expect_status 200 -c "$JAR" -b "$JAR" "$SITE/dashboard"
curl -s -b "$JAR" "$SITE/dashboard" > "$DASHBOARD"
grep -q 'id="product-ble"' "$DASHBOARD"
grep -q 'id="building-champ_ble"' "$DASHBOARD"
grep -q 'FermeEngine.js' "$DASHBOARD"
grep -q 'main.js' "$DASHBOARD"
rm -f "$DASHBOARD"
pass=$((pass + 3))

echo "== API : lecture =="
expect_status 200 "$API/"
expect_status 200 "$API/api/config"
expect_status 200 "$API/api/players/bean/inventory"
expect_status 200 "$API/api/players/bean/buildings"
expect_status 404 "$API/api/players/inconnu/inventory"
expect_status 404 "$API/api/players/bean/products/inconnu"

echo "== API : BREAD =="
UPDATED="$(curl -s -X PUT -H 'Content-Type: application/json' -d '{"stock":42}' "$API/api/players/bean/products/ble")"
[ "$(printf '%s' "$UPDATED" | json_get stock)" = "42" ]
[ "$(curl -s "$API/api/players/bean/products/ble" | json_get stock)" = "42" ]
pass=$((pass + 2))

UPDATED="$(curl -s -X PUT -H 'Content-Type: application/json' -d '{"level":2}' "$API/api/players/bean/generators/champ_ble")"
[ "$(printf '%s' "$UPDATED" | json_get level)" = "2" ]
pass=$((pass + 1))

echo "== API : récolte et amélioration =="
BEFORE="$(curl -s "$API/api/players/bean/inventory" | json_get ble)"
curl -s -X POST -H 'Content-Type: application/json' -d '{}' "$API/api/players/bean/buildings/champ_ble/harvest" > /dev/null
AFTER="$(curl -s "$API/api/players/bean/inventory" | json_get ble)"
[ "$AFTER" -gt "$BEFORE" ]
pass=$((pass + 1))

curl -s -X POST -H 'Content-Type: application/json' -d '{}' "$API/api/players/bean/buildings/champ_ble/upgrade" > /dev/null
[ "$(curl -s "$API/api/players/bean/generators/champ_ble" | json_get level)" = "3" ]
pass=$((pass + 1))

# Sans stock, l'amélioration doit être refusée par le serveur.
curl -s -X DELETE "$API/api/players/bean/products/ble" > /dev/null
expect_status 409 -X POST -H 'Content-Type: application/json' -d '{}' "$API/api/players/bean/buildings/champ_ble/upgrade"
expect_status 400 -X PUT -H 'Content-Type: application/json' -d '{"stock":-1}' "$API/api/players/bean/products/ble"

echo "== API : CORS =="
expect_status 204 -X OPTIONS \
    -H 'Origin: http://localhost:50180' \
    -H 'Access-Control-Request-Method: POST' \
    "$API/api/players/bean/buildings/champ_ble/harvest"
expect_status 405 -X OPTIONS \
    -H 'Origin: http://localhost:50180' \
    -H 'Access-Control-Request-Method: POST' \
    "$API/api/players/bean/inventory"
expect_status 404 -X OPTIONS -H 'Origin: http://localhost:50180' "$API/api/inconnu"

CORS_HEADERS="$(mktemp)"
curl -s -D "$CORS_HEADERS" -o /dev/null -H 'Origin: http://localhost:50180' "$API/api/players/bean/inventory"
grep -qi 'Access-Control-Allow-Origin: http://localhost:50180' "$CORS_HEADERS"
rm -f "$CORS_HEADERS"
pass=$((pass + 1))

echo "== API : météo et événements =="
expect_status 200 "$API/api/weather"
[ "$(curl -s "$API/api/weather" | json_get productionModifier)" != "" ]
pass=$((pass + 1))

EVENT="$(curl -s -X POST -H 'Content-Type: application/json' -d '{"type":"world","message":"Test de fumee."}' "$API/api/events")"
[ "$(printf '%s' "$EVENT" | json_get message)" = "Test de fumee." ]
expect_status 200 "$API/api/events"
pass=$((pass + 2))

echo "== API : SSE =="
curl -s --max-time 2 -D "$SSE_HEADERS" "$API/api/events/stream?after=0" > "$SSE_BODY" || true
grep -qi 'Content-Type: text/event-stream' "$SSE_HEADERS"
grep -q ': flux ouvert' "$SSE_BODY"
grep -q 'event: farm' "$SSE_BODY"
pass=$((pass + 3))

echo "Tous les tests sont passés ($pass vérifications)."
