# Test Coverage

## Commands

- Backend coverage: `composer test:coverage`
- Frontend coverage: `npm run test:frontend:coverage`

Backend coverage requires a PHP runtime with `xdebug` or `pcov` enabled. The GitHub Actions test workflow provisions Xdebug automatically, so CI is the source of truth for backend coverage artifacts when local PHP does not expose a coverage driver.

## Reporting Outputs

- Backend Clover XML: `storage/coverage/backend/clover.xml`
- Backend HTML report: `storage/coverage/backend/html`
- Frontend JSON summary: `storage/coverage/frontend/coverage-summary.json`
- Frontend HTML report: `storage/coverage/frontend/index.html`
- Frontend LCOV report: `storage/coverage/frontend/lcov.info`

## Current Gates

- Backend minimum line coverage: `20%`
- Frontend minimum statements: `1%`
- Frontend minimum lines: `1%`
- Frontend minimum functions: `0.5%`
- Frontend minimum branches: `0.5%`

These thresholds are intentionally conservative because the repository is moving from selective regression coverage to whole-app coverage. They exist to prevent silent regressions in the coverage pipeline while the broader suite is still expanding.

## Ratchet Policy

- Coverage thresholds may increase, but they should not decrease without explicit approval.
- When a module gains meaningful new tests, raise the relevant threshold in the same workstream if the suite remains stable.
- Prefer lifting thresholds in small, reviewable increments instead of making one large jump that becomes noisy or flaky.
- Keep backend and frontend thresholds independent so a weaker area does not block ratcheting in a stronger one.

## Intentional Gaps

- Frontend page-level coverage is still far below the near-complete target for large document pages, admin screens, and full modal-heavy flows.
- Browser-level smoke coverage is not yet implemented for critical end-to-end journeys.
- Backend coverage is broader across document flows, services, and policies, but CI-generated reports should be used to identify the next controller and export hotspots to target.
- Some frontend coverage is diluted by generated route/action files and very large page components; future waves should either cover them directly or scope the include list more precisely once the page suites exist.

## Next Ratchet Targets

- Raise frontend thresholds after invoice, quote, jobcard, report, and purchase-order page suites are added.
- Raise backend thresholds after CI confirms the expanded controller and service coverage baseline.
- Add browser smoke tests for the most business-critical create/edit/convert flows before pushing thresholds toward a high-quality steady state.
