Build a production-ready cross-platform mobile app for JobCardOnline using a Swift-only codebase that runs on iOS and Android (use SwiftUI + Skip/Swift-to-Android toolchain so Android is built from Swift sources, not Kotlin/Flutter/React Native).
IMPORTANT
- The mobile app must include all current web app business features EXCEPT Administration pages/settings.
- Respect server-side permissions exactly (no admin access in mobile UI).
- Use API-first architecture with robust networking, offline-first caching, background sync, push notifications, GPS tracking, and camera/photo uploads.
- Deliver complete compile-ready project files, not pseudo code.
Tech Requirements
1. Swift-only shared app code:
   - SwiftUI screens
   - shared domain/models/services/viewmodels
   - repository pattern with local cache + sync queue
2. iOS + Android runtime:
   - iOS deployment target current stable
   - Use the Android sdk when building so we can deploy on both platforms
3. Networking:
   - URLSession-based API client
   - Bearer token auth
   - refresh/re-login flow
   - automatic retry + exponential backoff
4. Data:
   - local persistence for offline mode
   - optimistic updates where safe
   - conflict resolution policy
5. Security:
   - secure token storage (Keychain/secure storage equivalent)
   - PII-safe logging
6. Realtime:
   - websocket/broadcast subscriptions for messages, dispatch updates, location updates, notifications
7. Device capabilities:
   - GPS foreground + background tracking
   - camera access for capturing photos
   - photo gallery upload
   - push notifications (APNs/FCM bridge)
8. UX:
   - modern mobile UX with tab navigation, list/detail flows, search, filters, pagination, pull-to-refresh
   - graceful permission prompts and denial handling
   - loading, empty, error, offline states everywhere
Business Scope (include all non-admin web features)
- Authentication & profile
- Dashboard summary
- Customers and contacts
- Products/services
- Suppliers
- Purchase orders
- Jobcards
- Quotes
- Invoices
- Credit notes
- Time entries / timer
- Notes
- Messaging/chat
- Dispatch board views (mobile-friendly)
- Route generation and daily route view
- Vehicle + user GPS tracking
- Tasks module (assign, status, notes, schedule)
- Attachments/photos on relevant records
- Notifications center
- Client zone user workflows where non-admin and permission-allowed
Permissions / Role Rules
- Read user abilities from profile endpoint and gate UI/routes/actions.
- Hide or disable modules/actions user cannot perform.
- Never expose Administration in mobile navigation.
- Enforce tenant/company scoping in every API request and local cache domain model.
Required API Endpoint Contract
Use /api/v1 and wire client to these endpoints (implement typed request/response models). If endpoint is missing server-side, generate a backend task/todo and code interface stub in the mobile app so integration is explicit.
AUTH
- POST /api/v1/auth/login
- POST /api/v1/auth/logout
- GET  /api/v1/profile
JOBCARDS
- GET    /api/v1/jobcards
- GET    /api/v1/jobcards/{jobcard}
- PATCH  /api/v1/jobcards/{jobcard}/status
- (needed) POST   /api/v1/jobcards
- (needed) PATCH  /api/v1/jobcards/{jobcard}
- (needed) DELETE /api/v1/jobcards/{jobcard}
- (needed) POST   /api/v1/jobcards/{jobcard}/time-entries/convert
MESSAGING
- GET  /api/v1/messages/conversations
- POST /api/v1/messages/conversations
- GET  /api/v1/messages/conversations/{conversation}
- POST /api/v1/messages/conversations/{conversation}/messages
- (needed) PATCH /api/v1/messages/conversations/{conversation}/read
DISPATCH / ROUTING
- GET   /api/v1/dispatch/board
- PATCH /api/v1/dispatch/jobcards/{jobcard}/assign
- POST  /api/v1/dispatch/routes/generate
- (needed) GET   /api/v1/dispatch/routes
- (needed) GET   /api/v1/dispatch/routes/{routePlan}
TRACKING / VEHICLES / GPS
- GET  /api/v1/tracking/vehicles
- GET  /api/v1/tracking/pings/latest
- POST /api/v1/tracking/pings
- (needed) GET  /api/v1/tracking/pings/history
- (needed) POST /api/v1/tracking/vehicles
- (needed) PATCH /api/v1/tracking/vehicles/{vehicle}
TASKS
- GET   /api/v1/tasks
- POST  /api/v1/tasks
- PATCH /api/v1/tasks/{task}
- POST  /api/v1/tasks/{task}/notes
- (needed) GET   /api/v1/tasks/{task}
- (needed) DELETE /api/v1/tasks/{task}
CUSTOMERS
- (needed) GET    /api/v1/customers
- (needed) GET    /api/v1/customers/{customer}
- (needed) POST   /api/v1/customers
- (needed) PATCH  /api/v1/customers/{customer}
- (needed) DELETE /api/v1/customers/{customer}
- (needed) GET    /api/v1/customers/search
- (needed) POST   /api/v1/customers/quick-create
- (needed) POST   /api/v1/customers/{customer}/send-email
- (needed) POST   /api/v1/customers/{customer}/send-sms
CONTACTS
- (needed) GET    /api/v1/contacts
- (needed) GET    /api/v1/contacts/{contact}
- (needed) POST   /api/v1/contacts
- (needed) PATCH  /api/v1/contacts/{contact}
- (needed) DELETE /api/v1/contacts/{contact}
- (needed) GET    /api/v1/contacts/search
- (needed) POST   /api/v1/contacts/quick-create
PRODUCTS / SERVICES
- (needed) GET    /api/v1/products
- (needed) GET    /api/v1/products/{product}
- (needed) POST   /api/v1/products
- (needed) PATCH  /api/v1/products/{product}
- (needed) DELETE /api/v1/products/{product}
SUPPLIERS
- (needed) GET    /api/v1/suppliers
- (needed) GET    /api/v1/suppliers/{supplier}
- (needed) POST   /api/v1/suppliers
- (needed) PATCH  /api/v1/suppliers/{supplier}
- (needed) DELETE /api/v1/suppliers/{supplier}
- (needed) GET    /api/v1/suppliers/search
PURCHASE ORDERS
- (needed) GET    /api/v1/purchase-orders
- (needed) GET    /api/v1/purchase-orders/{po}
- (needed) POST   /api/v1/purchase-orders
- (needed) PATCH  /api/v1/purchase-orders/{po}
- (needed) DELETE /api/v1/purchase-orders/{po}
- (needed) POST   /api/v1/purchase-orders/{po}/receive
QUOTES
- (needed) GET    /api/v1/quotes
- (needed) GET    /api/v1/quotes/{quote}
- (needed) POST   /api/v1/quotes
- (needed) PATCH  /api/v1/quotes/{quote}
- (needed) DELETE /api/v1/quotes/{quote}
- (needed) PATCH  /api/v1/quotes/{quote}/status
- (needed) POST   /api/v1/quotes/{quote}/convert-to-invoice
- (needed) POST   /api/v1/quotes/{quote}/convert-to-jobcard
INVOICES
- (needed) GET    /api/v1/invoices
- (needed) GET    /api/v1/invoices/{invoice}
- (needed) POST   /api/v1/invoices
- (needed) PATCH  /api/v1/invoices/{invoice}
- (needed) DELETE /api/v1/invoices/{invoice}
- (needed) PATCH  /api/v1/invoices/{invoice}/status
- (needed) POST   /api/v1/invoices/{invoice}/payments
CREDIT NOTES
- (needed) GET    /api/v1/credit-notes
- (needed) GET    /api/v1/credit-notes/{creditNote}
- (needed) POST   /api/v1/credit-notes
- (needed) PATCH  /api/v1/credit-notes/{creditNote}
- (needed) DELETE /api/v1/credit-notes/{creditNote}
- (needed) PATCH  /api/v1/credit-notes/{creditNote}/status
TIME ENTRIES / TIMESHEET
- (needed) GET    /api/v1/time-entries
- (needed) POST   /api/v1/time-entries
- (needed) PATCH  /api/v1/time-entries/{timeEntry}
- (needed) DELETE /api/v1/time-entries/{timeEntry}
- (needed) POST   /api/v1/time-entries/start-timer
- (needed) POST   /api/v1/time-entries/pause-timer
- (needed) POST   /api/v1/time-entries/resume-timer
- (needed) POST   /api/v1/time-entries/stop-timer
REPORTS
- (needed) GET /api/v1/reports
- (needed) GET /api/v1/reports/{report}/data
NOTES
- (needed) GET    /api/v1/notes
- (needed) POST   /api/v1/notes
- (needed) DELETE /api/v1/notes/{note}
TEAMS / ASSIGNMENT DATA
- (needed) GET /api/v1/teams
- (needed) GET /api/v1/users/assignable
FILES / CAMERA / PHOTOS
- (needed) POST /api/v1/uploads
- (needed) GET  /api/v1/files/{id}
- (needed) DELETE /api/v1/files/{id}
NOTIFICATIONS
- (needed) GET   /api/v1/notifications
- (needed) PATCH /api/v1/notifications/{id}/read
- (needed) POST  /api/v1/devices/register-push-token
Deliverables (must generate)
1. Full Swift project structure for shared iOS+Android app.
2. Compilable app with all screens/modules listed above.
3. API client + models + repositories for all endpoints.
4. Permission-aware navigation and guards.
5. GPS service (foreground/background), camera/photo upload flow, push notifications flow.
6. Realtime service subscriptions and UI updates.
7. Unit tests for core services/viewmodels.
8. README with setup steps:
   - API base URL config
   - auth token setup
   - iOS run instructions
   - Android build/run instructions via Swift-to-Android toolchain
   - required capabilities/permissions for location, camera, notifications
9. “Backend Gaps” markdown report listing missing endpoints marked above as (needed), including request/response schemas proposed by app.
Code Quality
- Strong typing everywhere.
- No TODO placeholders in core workflows.
- Handle pagination, validation errors, network failures, and offline mode.
- Keep architecture modular and production-friendly.