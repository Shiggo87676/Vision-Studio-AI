VISION STUDIO AI — PROJECT RULES

1. General Instructions

Understand the existing project structure before making changes.

Never rebuild existing functionality without a good reason.

Do not delete working code unnecessarily.

Make changes incrementally.

Follow the existing coding style and naming conventions.

Never assume a feature is complete without testing it.

Avoid unnecessary dependencies.

2. Development Workflow

Work in clearly defined phases.

Complete and test one phase before proceeding to the next.

Do not skip phases.

Before modifying files, explain what you intend to change.

After implementation, list all files created or modified.

Report errors and resolve them before continuing.

Do not leave placeholder implementations or unfinished features.

3. Database Rules

Use MySQL as the primary database.

Use secure database connections.

Use environment variables for database credentials.

Never hardcode database passwords.

Use prepared statements or a secure ORM.

Create proper database relationships and indexes.

Use database migrations for schema changes.

Never delete existing database tables or data without permission.

4. Authentication and Security

Implement secure user registration and login.

Implement logout functionality.

Implement forgot-password and reset-password functionality.

Implement change-password functionality.

Hash passwords securely using bcrypt or Argon2.

Use secure session management.

Validate and sanitize all user inputs.

Protect private routes and dashboard pages.

Never expose sensitive information in frontend code.

Implement appropriate CSRF protection where applicable.

Use secure error handling.

5. Frontend Rules

Build a modern, responsive interface.

Maintain consistent spacing, typography, and colors.

Ensure mobile, tablet, and desktop responsiveness.

Reuse components instead of duplicating code.

Implement proper loading, empty, success, and error states.

Make all buttons and interactive elements functional.

Avoid unnecessary UI complexity.

6. Backend Rules

Keep business logic separate from presentation logic.

Validate all incoming requests on the server.

Use proper HTTP status codes.

Implement centralized error handling.

Protect API endpoints with appropriate authentication and authorization.

Never trust frontend validation alone.

7. Code Quality

Write clean, readable, maintainable code.

Use descriptive variable and function names.

Avoid duplicated code.

Keep functions focused on one responsibility.

Use reusable services and components.

Fix existing errors before introducing new functionality.

Do not ignore warnings or TypeScript errors.

8. Testing Requirements

Test every major feature after implementation.

Test authentication flows.

Test database operations.

Test API endpoints.

Test responsive layouts.

Test invalid inputs and error scenarios.

Verify that existing features continue working after changes.

9. File Management

Respect the existing project structure.

Do not create unnecessary files.

Do not overwrite important configuration files without checking their contents.

Keep secrets in environment variables.

Maintain an updated .env.example file without real credentials.

10. Final Instruction

The main priority is a secure, stable, scalable, and maintainable Vision Studio AI application.

Always prioritize correctness over speed.

Never claim that a feature works unless it has been implemented and tested.

If a requirement is unclear, ask for clarification instead of making risky assumptions.

## Required PHP Runtime

- Use `C:\wamp64\bin\php\php8.3.28\php.exe` for every PHP operation in this project, including the development server, migrations, tests, and syntax checks.
- Never use the separately installed WinGet PHP executable.
- Run PHP commands from the VS Code workspace terminal, whose PATH is configured to prefer WAMP PHP, or invoke the WAMP executable by its full path.
- Composer scripts in `composer.json` explicitly invoke WAMP PHP 8.3.28.