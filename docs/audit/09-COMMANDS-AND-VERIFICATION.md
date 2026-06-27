# Commands and Verification

## Command ID: CMD-001

- **Purpose:** Check repository baseline — git status, branch, commit, tool versions
- **Working directory:** `/Users/Saiffil/Herd/commerce`
- **Command:** `git status && git log --oneline -1 && git branch --show-current && php -v && composer --version && node -v && npm -v`
- **Start state:** Clean
- **Result:** Passed
- **Exit code:** 0
- **Relevant output:** Branch `main`, commit `7d1dc95fa`, clean working tree (untracked `AUDIT.md` only). PHP 8.5.7, Composer 2.9.5, Node 22.23.1, npm 10.9.8.
- **Files changed:** None
- **Interpretation:** Baseline established. Repository is in a clean state.
- **Limitations:** None

## Command ID: CMD-002

- **Purpose:** Run PHPStan level 6 on cashier package
- **Working directory:** `/Users/Saiffil/Herd/commerce`
- **Command:** `./vendor/bin/phpstan analyse packages/cashier/src --level=6`
- **Start state:** Clean
- **Result:** Passed
- **Exit code:** 0
- **Relevant output:** `[OK] No errors` (97 files analysed)
- **Files changed:** None
- **Interpretation:** Static analysis passes at monorepo level 6. No package-specific phpstan config exists, relies on root config.
- **Limitations:** Tests/ directory doesn't exist, so PHPStan only covers src/

## Command ID: CMD-003

- **Purpose:** Run Pint (Laravel code style linter) on cashier package
- **Working directory:** `/Users/Saiffil/Herd/commerce`
- **Command:** `./vendor/bin/pint --test packages/cashier/src`
- **Start state:** Clean
- **Result:** Passed
- **Exit code:** 0
- **Relevant output:** `PASS` — all 97 files pass style checks
- **Files changed:** None
- **Interpretation:** Code style is consistent with Laravel conventions.
- **Limitations:** None

## Command ID: CMD-004

- **Purpose:** Run PHPStan level 6 on cashier-chip package
- **Working directory:** `/Users/Saiffil/Herd/commerce`
- **Command:** `./vendor/bin/phpstan analyse packages/cashier-chip/src --level=6`
- **Start state:** Clean
- **Result:** Passed
- **Exit code:** 0
- **Relevant output:** `[OK] No errors` (60 files analysed)
- **Files changed:** None
- **Interpretation:** Static analysis passes at level 6.
- **Limitations:** Tests/ has only infrastructure (Pest.php, TestCase.php), no test files to analyse.

## Command ID: CMD-005

- **Purpose:** Run Pint on cashier-chip package
- **Working directory:** `/Users/Saiffil/Herd/commerce`
- **Command:** `./vendor/bin/pint --test packages/cashier-chip/src`
- **Start state:** Clean
- **Result:** Passed
- **Exit code:** 0
- **Relevant output:** `PASS` — all 62 files pass style checks
- **Files changed:** None
- **Interpretation:** Code style is consistent with Laravel conventions.
- **Limitations:** None
