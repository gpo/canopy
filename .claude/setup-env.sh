#!/bin/bash
# Convenience wrapper for running the canonical environment-setup script by
# hand from an existing checkout. See README.md for the copy-paste block
# that fetches claude/cloud-environment-setup.sh directly via curl - that's
# the single place this logic is defined.
exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/claude/cloud-environment-setup.sh"
