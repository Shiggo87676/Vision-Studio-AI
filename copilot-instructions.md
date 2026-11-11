# VISION STUDIO AI — GITHUB COPILOT PROJECT INSTRUCTIONS

## 1. PROJECT OVERVIEW

You are the lead software engineer responsible for developing Vision Studio AI.

Build a secure, modern, scalable, maintainable AI-powered web application.

The application must include:
- User registration and authentication.
- Login and logout.
- Forgot password and password reset.
- Change password.
- Protected user dashboard.
- AI-powered functionality as defined by project requirements.
- User profile and account management.
- MySQL database integration.

Always inspect the existing project before making architectural decisions.

## 2. CRITICAL DEVELOPMENT RULES

- Use `C:\wamp64\bin\php\php8.3.28\php.exe` for all PHP operations in this project, including serving the application, migrations, tests and syntax checks. Never use WinGet PHP.
- Run PHP commands in the VS Code workspace terminal, which prepends WAMP PHP to PATH, or use the full WAMP executable path. Composer scripts are pinned to WAMP PHP.
- Never assume the project's technology stack.
- Inspect package.json, existing source files, database configuration and folder structure before coding.
- Preserve existing working functionality.
- Do not rewrite the entire application unnecessarily.
- Never delete files, tables, features or existing data without permission.
- Make small, controlled changes.
- Avoid unnecessary dependencies.
- Never create duplicate components, functions, APIs or database tables.
- Reuse existing utilities and components where appropriate.
- Explain significant architectural changes before implementing them.
- Ask questions when an important requirement is unclear.

## 3. DEVELOPMENT PHASES

Develop the application in clearly separated phases:

Phase 1: Project inspection and architecture.

Phase 2: Project setup and database configuration.

Phase 3: User registration and authentication.

Phase 4: Password recovery and account security.

Phase 5: Dashboard and application layout.

Phase 6: Core Vision Studio AI functionality.

Phase 7: API integration and backend functionality.

Phase 8: Testing, security and error handling.

Phase 9: Performance optimization and deployment preparation.

Do not skip phases.

Do not start a new phase until the current phase has been implemented, tested and verified.

## 4. DATABASE RULES

- Use MySQL as the primary database.
- Follow the existing database architecture where possible.
- Use secure database connections.
- Store credentials in environment variables.
- Never expose database credentials in frontend code.
- Use prepared statements or a secure ORM.
- Implement appropriate foreign keys and indexes.
- Use migrations for database schema changes.
- Validate database input.
- Use transactions where multiple related operations must succeed together.
- Never drop or reset production tables without explicit permission.
- Handle database errors gracefully.

## 5. AUTHENTICATION AND SECURITY

Implement secure authentication.

Requirements:
- Registration with appropriate validation.
- Login with secure credential verification.
- Logout with session invalidation.
- Forgot-password functionality.
- Secure password reset using expiring, single-use tokens.
- Change-password functionality.
- Secure password hashing using Argon2id or bcrypt.
- Protected dashboard routes.
- Appropriate session or token management.
- Rate limiting for sensitive authentication endpoints.
- CSRF protection where applicable.
- Secure cookie configuration where applicable.
- Server-side authorization checks.

Never store passwords in plain text.

Never expose password hashes, reset tokens, session secrets or private credentials.

Never rely exclusively on frontend validation.

## 6. FRONTEND DEVELOPMENT

- Create a professional, modern user interface.
- Ensure responsiveness on mobile, tablet and desktop.
- Maintain consistent spacing, colors, typography and components.
- Use reusable components.
- Avoid unnecessary code duplication.
- Implement loading indicators.
- Implement empty states.
- Implement success and error messages.
- Ensure buttons and interactive elements work correctly.
- Provide appropriate form validation.
- Maintain accessibility standards.

## 7. BACKEND DEVELOPMENT

- Separate business logic from presentation logic.
- Keep API routes organized.
- Validate all incoming requests.
- Apply authentication and authorization to protected endpoints.
- Use appropriate HTTP status codes.
- Implement centralized error handling.
- Never expose internal server errors or sensitive information to users.
- Keep secrets and private operations on the server.

## 8. AI INTEGRATION RULES

- Follow the actual AI functionality requirements provided for Vision Studio AI.
- Keep AI provider credentials on the backend.
- Never expose API keys in frontend code.
- Handle AI request failures gracefully.
- Implement appropriate request validation.
- Handle loading, processing and completed states.
- Avoid unnecessary repeated API calls.
- Implement usage limits where required.
- Keep AI integrations modular so providers can be changed without rebuilding the application.

## 9. CODE QUALITY

- Write clean, readable and maintainable code.
- Use descriptive naming conventions.
- Keep functions focused on one responsibility.
- Reuse existing components and services.
- Avoid unnecessary abstractions.
- Follow the conventions of the existing framework.
- Resolve errors rather than hiding them.
- Do not leave unfinished placeholder functionality.
- Do not introduce breaking changes without explaining them.

## 10. TESTING REQUIREMENTS

Test every significant feature.

At minimum, verify:
- User registration.
- Login with valid credentials.
- Login with invalid credentials.
- Logout.
- Forgot-password workflow.
- Password reset.
- Change password.
- Protected dashboard access.
- Unauthorized access prevention.
- Database operations.
- API validation.
- Frontend responsiveness.
- Error and loading states.

Run relevant tests after each implementation.

Do not claim a feature works unless it has been tested.

## 11. FILE MANAGEMENT

- Respect the existing folder structure.
- Keep related functionality organized.
- Do not overwrite important files without inspecting them.
- Do not modify unrelated files unnecessarily.
- Keep environment variables in .env.
- Maintain an accurate .env.example.
- Never commit real secrets or credentials.
- Update documentation when significant architecture changes occur.

## 12. REQUIRED WORKFLOW FOR EVERY TASK

Before implementing any task:

1. Read these project instructions.
2. Inspect the relevant existing files.
3. Understand how the existing functionality works.
4. Identify the files that need modification.
5. Explain the proposed implementation briefly.
6. Implement only the requested functionality.
7. Run relevant tests.
8. Fix errors introduced by the changes.
9. Report what was completed.
10. List files created or modified.
11. Report any remaining issues honestly.

## 13. IMPORTANT RESTRICTIONS

NEVER:
- Delete working functionality unnecessarily.
- Replace the existing technology stack without permission.
- Change database schemas recklessly.
- Hardcode passwords or API keys.
- Bypass authentication.
- Ignore security vulnerabilities.
- Install unnecessary packages.
- Claim tests passed without running them.
- Proceed with destructive operations without approval.

## 14. FINAL DEVELOPMENT PRINCIPLE

Correctness, security, stability and maintainability are more important than development speed.

Always understand the existing code before changing it.

Build Vision Studio AI incrementally, test every phase and preserve all working functionality.

If an implementation decision could cause data loss, security problems or breaking changes, stop and request approval first.

AI image generation.
Long-form AI video generation.
Downloadable generated images and videos.
Public access and reusable settings.
A responsive interface for phones and computers.


## ADVANCED FEATURES AND PLATFORM INTEGRATIONS

Build Vision Studio AI as a modular AI content creation and social media management platform.

### SOCIAL MEDIA INTEGRATIONS
Support official API integrations for:
- YouTube
- TikTok
- Instagram
- Facebook
- X
- LinkedIn
- Pinterest

Requirements:
- Secure account connections.
- OAuth authorization.
- Secure token storage and refresh.
- Multi-platform publishing dashboard.
- Platform-specific content previews.
- Caption and hashtag generation.
- Scheduling where supported.
- Publishing status and error reporting.
- Publishing history.
- Account disconnection.
- User approval before publishing.
- Respect API permissions, rate limits and platform policies.
- Provide manual export when direct publishing is unavailable.

### ADVANCED AI TOOLS
Include:
- Text-to-image.
- Image-to-image.
- Text-to-video.
- Image-to-video.
- Long-form video generation.
- AI video editing.
- AI voice generation.
- AI music and sound effects.
- AI subtitles and translation.
- AI avatars.
- AI storytelling.
- Character consistency.
- Background removal.
- AI thumbnail generation.
- Video resizing and formatting.
- AI content repurposing.

### CREATOR WORKSPACE
Include:
- User dashboard.
- Project library.
- Image and video editor.
- Content calendar.
- Saved prompts.
- Brand kits.
- Media storage.
- Download manager.
- Publishing history.
- Analytics.
- User settings.

### SECURITY
- Never expose API keys in frontend code.
- Store secrets in environment variables.
- Encrypt sensitive authentication tokens.
- Implement secure user authentication.
- Add role-based access controls.
- Validate uploaded files.
- Protect against unauthorized access.
- Provide account deletion and data export.
- Obtain user consent before publishing.

### SOFTWARE ARCHITECTURE
- Use a modular, maintainable architecture.
- Separate frontend and backend.
- Build reusable components.
- Create independent API integration modules.
- Use background jobs for lengthy AI generation tasks.
- Show generation progress.
- Support retrying failed jobs.
- Store project data in a database.
- Support scalable media storage.
- Include error handling and automated tests.

### DEVELOPMENT RULES
- Build one feature at a time.
- Do not overwrite existing project files without checking them.
- Explain major changes.
- Keep the application responsive on mobile and desktop.
- Use accessible interface components.
- Never use fake publishing success messages.
- Do not claim an AI feature works until it has been connected to a working model or service.
- Document installation and deployment steps.
