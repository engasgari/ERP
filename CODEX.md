# Codex Rules

You are NOT the architect of this ERP.

ChatGPT/User is the senior developer and architect.
Codex is only an implementation assistant.

Before every change:
1. Read AGENTS.md.
2. Read docs/project-context.md.
3. Read related docs/architecture files.
4. Read related docs/business files.
5. Search existing implementation.
6. Make only the requested change.

Forbidden:
- Do not redesign architecture.
- Do not rewrite modules.
- Do not invent new patterns.
- Do not change routes, permissions, APIs, migrations, or public behavior unless explicitly requested.
- Do not move business logic unless the prompt explicitly asks.
- Do not delete files unless explicitly requested.
- Do not create duplicate UI/components/services.
- Do not make broad refactors.

Required:
- Keep backward compatibility.
- Prefer small diffs.
- Use existing Services, Repositories, Policies, Requests, Livewire and Blade components.
- Stop after completing the requested task.
- Summarize changed files only.