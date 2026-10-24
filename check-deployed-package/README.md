# Check the deployed package

`run.sh` installs `logdash/php-sdk` from Packagist in Docker, sends a log of each level and a metric with it, and reads them back from Logdash.
CI runs it after each release, against the version it released.

```sh
LOGDASH_API_KEY=<api-key> LOGDASH_PERSONAL_API_KEY=<personal-api-key> VERSION=1.1.0 ./check-deployed-package/run.sh
```

- `LOGDASH_API_KEY` sends: the API key of the smoke-test project.
- `LOGDASH_PERSONAL_API_KEY` reads back: an `ldp_` personal API key with `logs:read` and `metrics:read`, restricted to that one project.
- `VERSION` picks the version, by default the latest. The check waits until Packagist serves it.
