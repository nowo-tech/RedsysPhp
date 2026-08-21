# Constitution — Nowo RedsysPhp

## Purpose

Clean-room MIT SDK for the **public** Redsys TPV Virtual protocol. Namespace `Nowo\Redsys\`. No redistribution of proprietary PHPL_* sources.

## Non-negotiables

1. Protocol algorithms and endpoints come from **public** Redsys documentation only.
2. `RedirectForm` returns HTML strings — never `echo` / `exit` (FrankenPHP worker-safe).
3. Always verify notification / REST signatures before fulfilling orders.
4. cURL REST calls MUST set connect and total timeouts.
5. PHPStan level 8 with empty `ignoreErrors`; FrankenPHP rulesets enabled.
6. Lines coverage on `src/` ≥ 99% (target 100%).
7. REQ-GIT-001: no Cursor co-author trailers in commits.
8. SemVer from **1.0.0**; no Composer `replace` of proprietary packages.
