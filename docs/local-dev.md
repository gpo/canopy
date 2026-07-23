# Local Development

## Prerequisites

- A container runtime — [OrbStack](https://orbstack.dev) (recommended) or [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)
- [pnpm](https://pnpm.io/installation) — package manager for front-end (theme and blocks plugin) dependencies

## Setup

```bash
git clone <repo-url>
cd canopy
ddev start
ddev composer install
pnpm install
```

The site will be available at `https://canopy.ddev.site`.

## Environment

| | |
|---|---|
| PHP | 8.4 |
| Database | MySQL 8.4 |
| Docroot | `web/` |
| JS package manager | pnpm |
| Front-end language | TypeScript (`.ts`/`.tsx` favoured over `.js`/`.jsx`) |

## Useful commands

```bash
ddev launch        # open the site in a browser
ddev stop          # stop containers
ddev restart       # restart after config changes
ddev ssh           # shell into the web container
ddev mysql         # MySQL shell
ddev wp            # WP-CLI
pnpm dev           # Vite dev server for the theme/blocks front end
pnpm build         # production front-end build
```
