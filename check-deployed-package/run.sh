#!/bin/sh
# Installs logdash/php-sdk from Packagist in Docker and checks that its logs and metrics reach Logdash, see README.md
set -eu
cd "$(dirname "$0")"

if [ -n "${VERSION:-}" ]; then
  echo "Waiting for Packagist to serve logdash/php-sdk $VERSION..."
  tries=0
  # Packagist lists the tag v1.1.0 as version "v1.1.0"
  until curl -fsS https://repo.packagist.org/p2/logdash/php-sdk.json | grep -F "\"version\":\"v$VERSION\"" > /dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 40 ]; then
      echo "Packagist does not serve logdash/php-sdk $VERSION after 10 minutes" >&2
      exit 1
    fi
    sleep 15
  done
fi

docker build --no-cache --build-arg VERSION="${VERSION:-}" -t logdash-php-sdk-check .
docker run --rm -e LOGDASH_API_KEY -e LOGDASH_PERSONAL_API_KEY logdash-php-sdk-check
