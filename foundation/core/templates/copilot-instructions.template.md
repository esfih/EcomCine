You are working in the {{PRODUCT_NAME}} repository.

Read README-FIRST.md first for every meaningful response.

Treat the root app-local files as the first routing layer.

When the repository uses shared foundation layers:

- use `foundation/core` for reusable workflow and AI-context guidance
- use `foundation/wp` only when the active task requires WordPress-specific runtime or packaging context
- keep `specs` and `specs/app-features` as the canonical app-local product truth

Use Git Bash as the default shell for repository work and PowerShell only for Windows-specific tasks.

Use one host Python 3 interpreter for repository scripts unless the app repo explicitly defines a different Python runtime model.

Before meaningful feature work, check the target feature `STATUS.md` and `feature-status.json`.