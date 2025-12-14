# Development Guidelines

- **NEVER** do any repo "cleanup" without explicit user instruction/permission.
- This includes (but is not limited to): `git restore`, `git checkout -- <path>`, `git reset`, `git clean`, removing
	untracked files, mass-reverting changes, or otherwise trying to "get back to a clean state".
	- If the working tree is messy or another agent is changing files: stop and ask what to do.
	- Before destructive changes, copy the file (e.g., `cp file.php file.php.bak`), then delete the backup when done.
	- Be smart about scope: identify the package for any file you touch and run tooling only for that package.
	- Pint: never run repo-wide; format only the affected package (e.g., `./vendor/bin/pint packages/inventory`).

	## Laravel Best Practices (Opinionated)

	- **Strictly enforce Laravel ways**: Reject generic PHP solutions if a "Laravel way" exists.
	- Use `Arr::get()` over `isset()`/`empty()`.
	- Use `Collections` over native arrays.
	- Use `Service Container` injection over `new Class()`.
	- Use `Model::create()`/`update()` over manual property assignment.
	- **Modern PHP**: Use PHP 8.2+ features (readonly classes, constructor injection, match expressions).

	## Architecture & Design Patterns

	- **SOLID Principles**: Adhere strictly to S.O.L.I.D.
	- **Action Classes**: Encapsulate all business logic in Action classes (e.g., `ApproveOrderAction`), **NEVER** in
	Controllers or Models.
	- Controllers should only validate input, call an Action, and return a response.
	- Models should only contain relationships, scopes, and simple accessors/mutators.
	- **Repository Pattern**: Use repositories for data access logic to separate it from business logic.
	- **Factory Pattern**: Use factories for complex object creation.

	## Naming Conventions (Strict)

	- **Classes**: `PascalCase` (e.g., `OrderController`)
	- **Methods**: `camelCase` (e.g., `calculateTotal`)
	- **Variables**: `camelCase` (e.g., `orderItems`)
	- **Constants**: `SCREAMING_SNAKE_CASE` (e.g., `MAX_RETRIES`)
	- **Database Tables**: `snake_case` plural (e.g., `order_items`)
	- **Database Columns**: `snake_case` (e.g., `user_id`)
	- **Booleans**: `is_`, `has_`, `can_` prefixes (e.g., `is_active`)