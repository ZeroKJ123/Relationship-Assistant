# Home Assistant repository

This workspace is connected to https://github.com/ZeroKJ123/Home-Assist.git.

## User-authorized Git workflow

- The user explicitly requests that completed fixes be committed and pushed to this GitHub repository immediately after appropriate verification. This authorization applies to future fixes in this project; do not ask for confirmation again for routine commits and pushes.
- Commit only files belonging to the requested task. Preserve unrelated user changes.
- Use the configured `origin` remote and the current task branch. Never force-push or overwrite remote changes.
- Keep credentials, tokens, `secrets.yaml`, private keys, runtime databases, logs, and backups out of Git. Inspect staged changes before committing.
- Report the commit and push result. If a push fails, preserve local work and explain the failure.

## Home Assistant configuration

The location and connection details of the running Home Assistant instance have not yet been supplied. Do not assume that this workspace is its live configuration directory. Obtain those details before changing or deploying live configuration.
