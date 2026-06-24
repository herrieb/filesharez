# Roadmap

The current state of FileShareZ is a solid single-tenant file-transfer platform with a personal library per user. The next major architectural step is multi-tenant orgs with a per-tenant encrypted library. This document lays it out as a real spec, not a wishlist.

## Priority order

| # | Title | Effort | Impact |
|---|---|---|---|
| 0 | **[Multi-tenant orgs + per-user encrypted library](#0-multi-tenant-orgs--per-user-encrypted-library)** | 4 weeks | High — turns the system from a single-user tool into a team platform |
| 1 | PHPUnit test suite (replace smoke tests) | 1 week | High — every change today is a manual regression test |
| 2 | Tenant-restricted share links | 1 week | Medium — closes the "anyone with a link can use it" gap |
| 3 | Library search (filename + small-text content) | 1 week | Medium — every multi-file library needs it |
| 4 | Image / video thumbnails in the library picker | 1 week | Medium — visual libraries become usable |
| 5 | Per-source ACL (share source with another user) | 1 week | High for teams |
| 6 | Virus scanning (ClamAV) | 1 week | High for production |
| 7 | Webhooks on transfer / library events | 1 week | Medium — turns the system into a platform |
| 8 | REST API with API keys | 2 weeks | High for integrations |
| 9 | Range request support on the download endpoint | 1 day | Low — only matters for CDN use |
| 10 | 2FA (TOTP) on user login | 1 week | High for production |

This document covers #0 in detail. The rest are intentionally short — they're well-understood features; what they need is time, not design.

## How to read this document

The #0 spec is structured as:

- **Goals** — what we're building and why
- **Current state** — what we have today that this replaces
- **Data model** — every new table and column
- **Encryption design** — the actual cryptographic primitives
- **Sharing** — how files move between users
- **Migration** — how the existing data crosses over
- **Out of scope** — what's explicitly deferred
- **Threat model** — what this protects against and what it doesn't
- **Build order** — the order to implement
