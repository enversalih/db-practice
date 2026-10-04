#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

say() {
    printf '\n%s\n' "$1"
}

fail() {
    printf '\nHata: %s\n' "$1" >&2
    exit 1
}

env_value() {
    sed -n "s/^$1=//p" .env | tail -n 1 | sed 's/^"//; s/"$//'
}

set_env_value() {
    key=$1
    value=$2
    temporary_file=".env.tmp.$$"

    awk -v key="$key" -v value="$value" '
        BEGIN { updated = 0 }
        $0 ~ "^" key "=" {
            if (! updated) {
                print key "=" value
                updated = 1
            }
            next
        }
        { print }
        END {
            if (! updated) {
                print key "=" value
            }
        }
    ' .env > "$temporary_file"

    mv "$temporary_file" .env
}

command -v docker >/dev/null 2>&1 || fail "Docker bulunamadı. Önce Docker Desktop veya Docker Engine kurun."
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 bulunamadı."
docker info >/dev/null 2>&1 || fail "Docker çalışmıyor. Docker'ı başlatıp scripti tekrar çalıştırın."

if [ ! -f .env ]; then
    cp .env.docker.example .env
    say ".env dosyası Docker varsayılanlarıyla oluşturuldu."
else
    say "Mevcut .env dosyası korunuyor."
fi

app_key=$(env_value APP_KEY)
if [ -z "$app_key" ]; then
    if command -v openssl >/dev/null 2>&1; then
        random_key=$(openssl rand -base64 32 | tr -d '\n')
    else
        random_key=$(dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64 | tr -d '\n')
    fi

    set_env_value APP_KEY "base64:$random_key"
    say "Yeni Laravel APP_KEY üretildi."
fi

say "Docker image'ları hazırlanıyor..."
docker compose pull postgres
docker compose build --pull app

say "Uygulama ve PostgreSQL başlatılıyor..."
docker compose up -d --remove-orphans

app_container=$(docker compose ps -q app)
[ -n "$app_container" ] || fail "Uygulama container'ı oluşturulamadı."

timeout_seconds=${SETUP_TIMEOUT:-600}
elapsed=0

while [ "$elapsed" -lt "$timeout_seconds" ]; do
    health_status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$app_container" 2>/dev/null || true)

    case "$health_status" in
        healthy)
            break
            ;;
        exited|dead|unhealthy)
            docker compose logs --tail=100 app >&2
            fail "Uygulama sağlıklı biçimde başlatılamadı."
            ;;
    esac

    printf '.'
    sleep 2
    elapsed=$((elapsed + 2))
done

printf '\n'

if [ "$elapsed" -ge "$timeout_seconds" ]; then
    docker compose logs --tail=100 app >&2
    fail "Uygulama ${timeout_seconds} saniye içinde hazır olmadı. SETUP_TIMEOUT değerini artırabilirsiniz."
fi

app_port=$(env_value APP_PORT)
db_port=$(env_value DB_EXPOSE_PORT)
app_port=${app_port:-8090}
db_port=${db_port:-54329}

say "Kurulum tamamlandı."
printf 'Uygulama:  http://localhost:%s\n' "$app_port"
printf 'PostgreSQL: localhost:%s\n' "$db_port"
printf 'Loglar:     docker compose logs -f app\n'
