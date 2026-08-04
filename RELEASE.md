# Releasing `seatlayer/seatlayer-php` to Packagist

Maintainer runbook. This file is `export-ignore`d and never reaches a consumer's
`vendor/` directory.

> **The single most important fact: Packagist versions come from git TAGS, not
> from `composer.json`.** There is no `version` field in our `composer.json` and
> there must not be one — Packagist derives `0.1.0` from the tag `v0.1.0`.
> Editing `composer.json` and pushing `main` publishes **nothing**. Only a tag
> does. Correspondingly, a tag pointing at the wrong commit publishes the wrong
> tree, silently.

---

## 1. Accounts and credentials needed

| What | Where | Notes |
|---|---|---|
| Packagist account | https://packagist.org/register | Can sign in with GitHub — simplest, because submission needs to prove you own the repo. |
| GitHub repo `seatlayer/seatlayer-php` | https://github.com/seatlayer/seatlayer-php | Must exist and be **public**. Packagist cannot read a private repo without a paid Private Packagist plan. |
| Push rights to that repo | GitHub | Needed for `git push` and `git push --tags`. |
| Packagist auto-update hook | See §4 | Without it, Packagist only re-crawls on a slow poll and new tags appear late. |

No API token is needed for the *initial* submission — it is a web-form action.
A token is only needed if you want to trigger updates via the Packagist API
instead of the GitHub hook.

**Prerequisite check (verified 2026-08-04):** `seatlayer/seatlayer-php` currently
404s on Packagist, i.e. the name is unclaimed. Vendor name `seatlayer` is claimed
by whoever registers first — if the vendor was taken between then and now, the
package name has to change, and the WordPress plugin's `composer require` line
has to change with it.

---

## 2. Publish, in order

### 2.0 Point the tag at the release commit — DO THIS FIRST

The `v0.1.0` tag was created before the packaging fixes (`.gitattributes`,
`authors`/`support` metadata). **If the tag is not moved, Packagist will publish a
dist that ships `tests/`, `phpunit.xml` and `.github/` into every consumer.**

```bash
cd /path/to/seatlayer-php
git tag -f v0.1.0 main           # re-point to the release commit
git push origin main
git push --force origin v0.1.0   # plain `git push --tags` will NOT update an
                                 # existing remote tag; it warns and moves on
```

Confirm the tag is on the right commit before continuing:

```bash
git rev-list -n1 v0.1.0   # must equal `git rev-parse main`
```

### 2.1 Pre-flight (all must be green)

```bash
composer validate --strict          # expect: ./composer.json is valid
composer install
vendor/bin/phpstan analyse          # level 8, expect: No errors
vendor/bin/phpunit                  # expect: OK (33 tests, 66 assertions)
```

### 2.2 Inspect exactly what will ship

Packagist builds the dist with `git archive`, honouring `export-ignore`:

```bash
git archive v0.1.0 | tar -t | sort
```

Expected — and nothing else:

```
CHANGELOG.md
LICENSE
README.md
composer.json
src/…            (13 files)
```

If `tests/`, `phpunit.xml`, `phpstan.neon`, `AGENTS.md`, `RELEASE.md` or
`.github/` appear, `.gitattributes` did not take effect — you are almost
certainly archiving a tag that predates it. Go back to §2.0.

### 2.3 Submit (one time only)

1. Go to https://packagist.org/packages/submit
2. Paste `https://github.com/seatlayer/seatlayer-php`
3. Submit.

Packagist reads `composer.json` from the default branch, then imports **every**
tag matching `vX.Y.Z` as a released version. `0.1.0` should appear within seconds.

Every release after this one is just: commit, tag, push the tag. Never the web
form again.

---

## 3. Verify a clean install from Packagist

In an **empty** directory — not the SDK checkout, or you will test the local
tree instead of the registry:

```bash
mkdir /tmp/sl-verify && cd /tmp/sl-verify
composer init --no-interaction --name=seatlayer/verify
composer require seatlayer/seatlayer-php:^0.1
```

Then confirm the three things that actually matter:

```bash
# 1. The dist is clean — no tests, no CI config.
find vendor/seatlayer/seatlayer-php -type f | sort

# 2. PSR-4 autoloading resolves.
php -r 'require "vendor/autoload.php";
        echo SeatLayer\HttpClient::DEFAULT_BASE_URL, PHP_EOL;'
# expect: https://api.seatlayer.io

# 3. The class instantiates and rejects a publishable key.
php -r 'require "vendor/autoload.php";
        try { new SeatLayer\SeatLayer("pk_test_x"); echo "BAD: accepted pk_\n"; }
        catch (Throwable $e) { echo "OK: ", $e->getMessage(), PHP_EOL; }'
```

A live smoke test needs a real `sk_test_…` key:

```bash
SEATLAYER_SECRET_KEY=sk_test_… php -r 'require "vendor/autoload.php";
  $c = new SeatLayer\SeatLayer(getenv("SEATLAYER_SECRET_KEY"));
  var_dump($c->mode); print_r($c->events->list(limit: 1));'
```

---

## 4. Auto-update hook

If you signed in to Packagist with GitHub, Packagist installs the hook itself and
there is nothing to do. Verify on the package page — it should not show the
"package is not auto-updated" warning.

Otherwise, on GitHub: **Settings → Webhooks → Add webhook**

- Payload URL: `https://packagist.org/api/github?username=<packagist-username>`
- Content type: `application/json`
- Secret: your Packagist API token (Packagist profile → Show API token)
- Events: *Just the push event*

Until this is wired up, a pushed tag can take hours to surface.

---

## 5. If it goes wrong

| Situation | What you can do | Irreversible? |
|---|---|---|
| Bad tag content, **never installed by anyone** | Delete the tag on GitHub (`git push --delete origin v0.1.0`), then delete the version on Packagist. Re-tag and re-push. | No — but see the caveat below. |
| Bad release, already in the wild | **Do not reuse the version number.** Tag `v0.1.1` with the fix. | Effectively yes. |
| Wrong package name / vendor | Delete the package on Packagist (package page → Settings → Delete), resubmit under the new name. | Name is freed, but anything already depending on the old name breaks. |
| Secret committed | Rotate the secret first — deleting the tag does not un-fetch it from anyone who already cloned. | Yes, assume disclosure. |

Caveats worth knowing before you need them:

- **Packagist mirrors the tag; it does not store its own copy of your code.**
  Delete the git tag and the Packagist version stops resolving — which breaks
  anyone who pinned it. That is why re-tagging is only safe before real installs.
- **A deleted Packagist package name can be re-registered by anyone**, including
  someone who is not you. Do not delete `seatlayer/seatlayer-php` casually; this
  is a supply-chain hole, not just an inconvenience.
- Packagist has no "unlist". The choices are: leave it, or delete it.
- There is no yank-with-tombstone like crates.io or RubyGems.

---

## 6. Next version

Bump nothing in `composer.json` — there is no version there by design. The
release *is* the tag.

```bash
# edit CHANGELOG.md, commit
git tag v0.1.1
git push origin main --follow-tags
```

Keep this SDK's number aligned with the other **server** SDKs
(`@seatlayer/server`, `seatlayer` on PyPI/RubyGems, `seatlayer-java`,
`seatlayer-go`, `SeatLayer` on NuGet), which are all on the 0.1.x line. The
0.4x.x line belongs to the **browser** SDK family (`@seatlayer/js`, `/react`,
`/vue`, `/angular`) and is a different product with its own cadence — do not
align to it.
