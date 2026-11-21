# AGENTS.md — LARAS BUMI OS

## Project Identity

This repository contains Laras Bumi OS, an integrated waste management operations system for CV Laras Bumi Grup in Jember, Indonesia.



The project is intended for real business operations, not a demonstration-only application.

## Mandatory Instructions

1. Read this AGENTS.md before modifying the repository.
2. Read docs/architecture.md, docs/requirements.md, and docs/progress.md before starting a new phase.
3. Follow the approved technology stack.
4. Preserve the modular monolith architecture.
5. Keep business logic on the backend.
6. Follow the documented role permissions.
7. Do not bypass validation or authorization.
8. Do not introduce breaking database changes without migrations.
9. Do not overwrite historical financial transactions.
10. Do not modify posted inventory ledger entries directly.
11. Do not use actual business projections as production transactions.
12. Do not introduce mock data into real operational flows.
13. Keep user-facing interfaces in Bahasa Indonesia.
14. Use IDR, kilograms, and Asia/Jakarta local presentation.
15. Maintain complete API and database documentation.
16. Run appropriate tests after every implementation.
17. Report test failures and incomplete requirements.
18. Update docs/progress.md after each completed task.
19. Do not implement unrelated features without authorization.
20. Do not deploy to production without explicit approval.

## Required Domain Invariants

- Pickup completion and facility weight verification are separate events.
- One waste intake can be associated with multiple pickup sources.
- Estimated weights must never be represented as verified.
- Stock movement records are append-only after posting.
- Inventory balance cannot become negative.
- Material source lineage must be preserved.
- Production input consumption must be transactional.
- QC-pending output must not be treated as released finished goods.
- Billing jobs must be idempotent.
- Issued invoices must preserve historical prices.
- Financial corrections require authorized workflows.
- Critical mutations must be auditable.

## Coding Standards

- Use strict typing where supported.
- Apply consistent formatting and linting.
- Prefer small, focused modules.
- Use database transactions for critical state changes.
- Validate every external input.
- Enforce authorization server-side.
- Keep secrets in environment variables.
- Use migrations for schema changes.
- Avoid unnecessary dependencies and abstractions.
- Use structured logging for operational failures.

## Definition of Done

A feature is complete only when:

- Business rules are implemented.
- Persistence works.
- API endpoints work.
- Frontend is connected to the actual API.
- Authorization is enforced.
- Error handling is implemented.
- Automated tests exist and pass.
- Documentation is updated.
- Known limitations are disclosed.

## Working Procedure

For each task:

1. Inspect relevant files and requirements.
2. Explain the intended changes briefly.
3. Implement the feature.
4. Run focused tests.
5. Run broader checks where appropriate.
6. Report actual test results.
7. List changed files.
8. Update project progress.
9. Identify unresolved issues.

Do not claim a command or test was executed if it was not.

## Phase Control

Do not automatically advance into a new major development phase.



Complete the currently assigned phase and stop for review.



A deployment to staging or production must not happen without explicit instructions.

## User Experience

- Modern enterprise interface.
- Responsive desktop and mobile layouts.
- Accessible components.
- Clear loading, empty and error states.
- Consistent Laras Bumi branding.
- Mobile-first driver interface.
- Reliable offline synchronization.

## Security and Safety

- No credentials or secrets in commits.
- No unapproved destructive data operations.
- No unauthorized production access.
- No real personal information in automated tests.
- No disabling security checks to hide failures.
- Always surface security concerns and unresolved risks.

## Final Task Report Format

For each task provide:

1. Completed requirements
2. Changed files
3. Database changes
4. API changes
5. UI changes
6. Tests executed and results
7. Known issues
8. Next recommended task

Keep reports factual and concise.
