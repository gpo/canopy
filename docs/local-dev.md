# Local Development

## Prerequisites

- A container runtime — [OrbStack](https://orbstack.dev) (recommended) or [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- [DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/)

## Setup

```bash
git clone <repo-url>
cd canopy
ddev start
ddev composer install
```

The site will be available at `https://canopy.ddev.site`.

## Environment

| | |
|---|---|
| PHP | 8.4 |
| Database | MySQL 8.4 |
| Docroot | `web/` |

## Useful commands

```bash
ddev launch        # open the site in a browser
ddev stop          # stop containers
ddev restart       # restart after config changes
ddev ssh           # shell into the web container
ddev mysql         # MySQL shell
ddev wp            # WP-CLI
```
