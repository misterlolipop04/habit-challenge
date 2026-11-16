#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v docker >/dev/null 2>&1; then
    echo 'Docker не найден. Сначала запустите Docker Desktop.' >&2
    exit 1
fi
if ! command -v python3 >/dev/null 2>&1; then
    echo 'Для подготовки .env нужен python3.' >&2
    exit 1
fi

echo 'Настройка локального администратора Habit Challenge'
echo 'Пароль не будет показан и не попадёт в историю терминала.'

read -r -s -p 'Придумайте пароль (не менее 12 символов): ' password
printf '\n'
if (( ${#password} < 12 )); then
    echo 'Пароль должен содержать не менее 12 символов.' >&2
    exit 1
fi
read -r -s -p 'Повторите пароль: ' password_confirm
printf '\n'
if [[ "$password" != "$password_confirm" ]]; then
    echo 'Пароли не совпадают.' >&2
    exit 1
fi
unset password_confirm

echo 'Проверяем контейнер PHP...'
docker compose up -d --build web

# Передаём пароль через stdin: его нет в аргументах процессов или файлах.
hash="$(printf '%s' "$password" | docker compose exec -T web php -r \
    'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;')"
unset password

if [[ -z "$hash" ]]; then
    echo 'Не удалось сформировать хеш пароля.' >&2
    exit 1
fi

# Не перезаписываем другие значения из существующего .env.
HC_ADMIN_HASH="$hash" python3 - <<'PY'
from pathlib import Path
import os

env = Path('.env')
lines = env.read_text(encoding='utf-8').splitlines() if env.exists() else []
lines = [line for line in lines if not line.startswith(('ADMIN_ENABLED=', 'ADMIN_PASSWORD_HASH='))]
lines.extend([
    'ADMIN_ENABLED=1',
    "ADMIN_PASSWORD_HASH='" + os.environ['HC_ADMIN_HASH'] + "'",
])
env.write_text('\n'.join(lines) + '\n', encoding='utf-8')
PY
chmod 600 .env

# Сохраняем существующие правила игнорирования, добавляем только .env.
if [[ ! -f .gitignore ]] || ! grep -Eq '^\.env$' .gitignore; then
    printf '\n# Секреты локальной среды\n.env\n' >> .gitignore
fi

docker compose up -d --force-recreate web

echo
echo 'Готово. Админ-панель: http://localhost:8081/admin/'
echo 'Входите с паролем, который только что ввели.'
