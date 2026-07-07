# canopy


## Claude Code cloud environments

Sessions in [Claude Code on the web](https://claude.ai/code) install this repo's dependencies at session start via `.claude/hooks/session-start.sh`. To pre-bake them into the cached environment image instead (sessions start warm, and install failures surface at environment build rather than mid-task), add this line to the environment's **Setup script** field, alongside the equivalent line from any other attached repo you want pre-built:

```bash
bash /home/user/canopy/.claude/setup-env.sh || true
```

The path matches where Claude Code cloud environments clone this repo.
