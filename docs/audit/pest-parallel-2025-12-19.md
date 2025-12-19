---
title: Pest Parallel Run (2025-12-19)
---

This file captures a full parallel test run output to make debugging easier by referencing a single, committed artifact.

## How this was generated
- Command:
  - `php -d max_execution_time=0 ./vendor/bin/pest --parallel 2>&1 | tee /tmp/pest-parallel-2.txt`
- Captured output (raw): [docs/audit/pest-parallel-2025-12-19.txt](docs/audit/pest-parallel-2025-12-19.txt)

## Summary (from the tail of the output)
- Tests: 678 failed, 3 risky, 37 skipped, 14587 passed (32733 assertions)
- Duration: 4025.31s
- Parallel: 8 processes

## Notes
- This is intentionally stored as a plain `.txt` file so line numbers and stack traces are preserved exactly.
- To make this publicly accessible, commit and push these files to the repository.
