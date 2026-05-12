# Mobile App Prompt for Xcode ChatGPT

Below is a comprehensive prompt you can paste into Xcode's AI chat (or any AI assistant) to generate your cross-platform Swift mobile app using the Swift SDK for Android.

---

## PROMPT

```
I want to build a cross-platform mobile app (iOS and Android) using Swift with the Swift SDK for Android.
The app is a Jobcard management system that integrates with an existing Laravel/PHP web application.

---

## 1. PROJECT OVERVIEW

**Project Name:** RevampJobCardMobile
**Bundle ID (iOS):** com.revampjobcard.app
**Package Name (Android):** com.revampjobcard.app

**Core Functionality:** A mobile-first jobcard management app that allows field technicians to view, update, and manage jobcards. Authentication, permissions (ACL), and business logic are handled by the web app API. The mobile app is a thin client.

**Target Platforms:**
- iOS 15+
- Android API 28+ (using Swift SDK for Android)

**Swift SDK for Android:** Follow the setup guide at https://www.swift.org/documentation/articles/swift-sdk-for-android-getting-started.html. Use cross-compilation with `swift build --swift-sdk aarch64-unknown-linux-android28`.

---

## 2. AUTHENTICATION

**Auth Type:** Token-based authentication using Laravel Sanctum.

**Login Flow:**
1. User enters email and password
2. App sends credentials + `device_name` (e.g., "iPhone 15 - John" or "Pixel 8 - Jane") to the API
3. API returns a Bearer token
4. App stores token securely in the device Keychain (iOS) / Keystore (Android)
5. All subsequent API requests include `Authorization: Bearer {token}` header

**Login Endpoint:**
```
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "secretpassword",
    "device_name": "iPhone 15 - John"
}
```

**Response:**
```json
{
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "user_type": "standard"
    }
}
```

**Important:** The `device_name` field is required and should identify the device (e.g., "iPhone 15 - John"). This is stored alongside the push token and shown in the web app's device management.

**Logout Endpoint:**
```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

**Logout:** Delete token from device storage and redirect to login screen.

---

## 3. BIOMETRIC AUTHENTICATION

**Feature:** Support biometric authentication (Face ID / Touch ID on iOS, fingerprint on Android) as an additional security layer.

**Implementation:**
- On supported devices, after initial token-based login, allow users to enable biometric login
- Store a flag in UserDefaults/SharedPreferences indicating biometric is enabled
- When biometric is enabled, on app launch use `LAContext` (iOS) / `BiometricPrompt` (Android) to authenticate before auto-filling the stored token
- If biometric fails after 3 attempts, fall back to manual token entry
- The web app does NOT need to know about biometric - it's purely a local device security feature

---

## 4. DEVICE REGISTRATION FOR PUSH NOTIFICATIONS

**Purpose:** When a user logs in, register the device with the web app so it can receive push notifications.

**Registration Endpoint:**
```
POST /api/v1/devices/register-push-token
Authorization: Bearer {token}
Content-Type: application/json

{
    "token": "fcm_or_apns_token_here",
    "platform": "ios",
    "device_name": "iPhone 15 - John"
}
```

**Response:**
```json
{
    "message": "Device registered"
}
```

**List Devices Endpoint:**
```
GET /api/v1/devices
Authorization: Bearer {token}
```

**Response:**
```json
[
    {
        "id": 1,
        "platform": "ios",
        "device_name": "iPhone 15 - John",
        "last_active_at": "2026-05-12T10:30:00Z"
    }
]
```

**When to Register:**
- After successful login, check if we have a push token
- If yes, call register-push-token endpoint
- If token changes (user reinstalls app, etc.), re-register

---

## 5. PUSH NOTIFICATIONS (NATIVE)

**Platforms:** APNs (iOS) and Firebase Cloud Messaging (Android)

**iOS:**
- Use `UserNotifications` framework
- Request authorization on first launch: `UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound])`
- Register for remote notifications: `UIApplication.shared.registerForRemoteNotifications()`
- Handle `didRegisterForRemoteNotificationsWithDeviceToken` to get the APNs token

**Android:**
- Use Swift SDK for Android notification support
- Register with FCM to get a token
- Pass token to `POST /api/v1/devices/register-push-token`

**Notification Handling:**
- When app receives a notification, check if user is logged in
- If logged in, navigate to relevant jobcard based on notification payload
- Payload structure: `{ "type": "jobcard", "entity_id": 123, "title": "Jobcard assignment: Fix AC Unit" }`

**Mark as Read Endpoint:**
```
PATCH /api/v1/notifications/{id}/read
Authorization: Bearer {token}
```

---

## 6. JOB CARD MODULE (Phase 1 Only)

This is the ONLY module needed for phase 1. Focus all functionality here.

### 6.1 List Jobcards

**Endpoint:**
```
GET /api/v1/jobcards
Authorization: Bearer {token}
```

**Query Parameters:**
- `status` - filter by status (optional)
- `assigned_to_user_id` - filter by assigned user (optional)
- `date_from`, `date_to` - filter by date range (optional)
- `per_page` - pagination (default 20)

**Jobcard Statuses:**
`new`, `needs_scheduling`, `scheduled`, `dispatched`, `accepted`, `en_route`, `on_site`, `paused`, `waiting_for_parts`, `needs_follow_up`, `emergency`, `completed`, `cancelled`

**Response Model:**
```json
{
    "data": [
        {
            "id": 1,
            "job_number": "JC-2026-0001",
            "title": "Fix AC Unit",
            "description": "Customer reported AC not cooling...",
            "status": "scheduled",
            "priority": "high",
            "scheduled_start_at": "2026-05-12T09:00:00Z",
            "scheduled_end_at": "2026-05-12T11:00:00Z",
            "customer": {
                "id": 1,
                "name": "John Smith",
                "phone": "+1234567890"
            },
            "service_address": "123 Main St, City, State 12345",
            "assigned_to_user": {
                "id": 1,
                "name": "Jane Tech"
            }
        }
    ],
    "links": {...},
    "meta": {...}
}
```

**UI:** Show list with job number, title, status badge, scheduled date/time, customer name. Pull-to-refresh. Infinite scroll pagination.

### 6.2 View Jobcard Details

**Endpoint:**
```
GET /api/v1/jobcards/{id}
Authorization: Bearer {token}
```

**Response:** Full jobcard object with all fields including line items, time entries, etc.

**UI:** Scrollable detail view with sections:
- Header: Job number, title, status, priority
- Customer info card
- Service address (with map button)
- Scheduled times
- Description
- Assigned user/team
- Line items table
- Time entries list
- Action buttons at bottom

### 6.3 Update Jobcard Status

**Endpoint:**
```
PATCH /api/v1/jobcards/{id}/status
Authorization: Bearer {token}
Content-Type: application/json

{
    "status": "on_site"
}
```

**Allowed Transitions (validate locally):**
- `dispatched` -> `accepted`
- `accepted` -> `en_route`
- `en_route` -> `on_site`
- `on_site` -> `paused` | `completed`
- `paused` -> `on_site` | `waiting_for_parts`
- `waiting_for_parts` -> `on_site` | `needs_follow_up`
- `needs_follow_up` -> `scheduled`
- `emergency` -> `en_route`

**UI:** Status button at bottom of detail screen. Show confirmation dialog before changing status. Show spinner during API call.

### 6.4 Create Time Entry

**Endpoint:**
```
POST /api/v1/time-entries
Authorization: Bearer {token}
Content-Type: application/json

{
    "jobcard_id": 1,
    "date": "2026-05-12",
    "start_time": "09:00",
    "end_time": "11:00",
    "description": "Installed new compressor unit",
    "is_billable": true
}
```

**UI:** On jobcard detail screen, show "Log Time" button. Open form with date picker, start/end time pickers, description text field, billable toggle. Save button.

### 6.5 View Time Entries

**Endpoint:**
```
GET /api/v1/jobcards/{id}/time-entries
Authorization: Bearer {token}
```

**UI:** On jobcard detail screen, show time entries section below jobcard info.

---

## 7. LOCATION SERVICES (Dispatch Integration)

**Purpose:** The mobile app sends real GPS coordinates to the web app's dispatch module, replacing the current test coordinates from environment variables. The web app's dispatch map reads these pings via the `UserLocationProvider` service.

**Server Configuration:**
- `DISPATCH_USE_REAL_LOCATIONS=true` (default) — dispatch board shows real GPS pings from mobile app
- `DISPATCH_USE_REAL_LOCATIONS=false` — dispatch board falls back to `DISPATCH_TEST_USER_LOCATIONS` test coordinates

### 7.1 Location Tracking

**Implementation:**
- Request location permissions: `NSLocationWhenInUseUsageDescription` (iOS), `ACCESS_FINE_LOCATION` (Android)
- Use `CLLocationManager` (iOS) / Swift SDK location APIs (Android)
- Track location continuously when user is on a jobcard with status `en_route` or `on_site`
- Send location pings every 30 seconds to the API

**Location Ping Endpoint:**
```
POST /api/v1/tracking/pings
Authorization: Bearer {token}
Content-Type: application/json

{
    "latitude": 37.7749,
    "longitude": -122.4194,
    "speed": 15.5,
    "heading": 180.0,
    "accuracy": 10.0,
    "jobcard_id": 1,
    "recorded_at": "2026-05-12T10:30:00Z"
}
```

### 7.2 Location Permissions

**iOS - Info.plist:**
```xml
<key>NSLocationWhenInUseUsageDescription</key>
<string>JobCardMobile needs your location to update the dispatch system and help coordinate service visits.</string>
<key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
<string>JobCardMobile needs your location to track your position while working on jobcards.</string>
```

**Android - AndroidManifest.xml:**
```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />
```

**Permission Flow:**
1. On first launch, explain why location is needed
2. Request permission
3. If denied, show settings button to guide user to enable
4. If granted, start location updates

### 7.3 Location Handling When App is Backgrounded

- iOS: Use `BGTaskScheduler` for background location updates
- Android: Use `ForegroundService` to keep tracking alive
- When app is backgrounded, continue sending location pings every 60 seconds
- Show persistent notification indicating location tracking is active

---

## 8. OFFLINE SUPPORT (Basic)

**Requirement:** The app must handle poor network conditions gracefully.

**Implementation:**
- Use `URLSession` with automatic retry (3 attempts with exponential backoff)
- Show toast/alert when API call fails due to network error
- Cache last-fetched jobcard list in local storage (UserDefaults/SQLite)
- When offline, show cached list with "Offline" badge
- Queue status changes and time entries when offline, sync when online
- Use `NetworkMonitor` (NWPathMonitor on iOS, Connectivity on Android) to detect online/offline state

---

## 9. ERROR HANDLING

**API Error Response Format:**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "status": ["The selected status is invalid."]
    }
}
```

**UI Error Handling:**
- 401 Unauthorized: Clear token, redirect to login
- 403 Forbidden: Show alert "You don't have permission for this action"
- 404 Not Found: Show alert "Jobcard not found"
- 422 Validation Error: Show inline field errors
- 500 Server Error: Show generic "Something went wrong. Please try again."
- Network Error: Show "No internet connection" with retry button

---

## 10. TECHNICAL REQUIREMENTS

**Swift Version:** 6.x
**Xcode:** 15+
**Architecture:** MVVM with Combine for reactive data flow
**UI Framework:** SwiftUI (iOS) + SwiftUI (Android via Swift SDK)
**Networking:** URLSession with async/await
**Local Storage:** UserDefaults for settings, SQLite.swift for jobcard cache
**Security:** Store tokens in iOS Keychain / Android Keystore
**Biometrics:** LocalAuthentication (iOS), AndroidX Biometric (Android)

**Key Dependencies (Swift Package Manager):**
- SQLite.swift - for local database
- KeychainAccess - for secure token storage

**API Base URL:** Configure in app settings (for development: `http://localhost:8000/api/v1/`)

---

## 11. SCREENS SUMMARY

| Screen | Description |
|--------|-------------|
| LoginScreen | Email/password fields, login button, biometric option if enabled |
| JobcardListScreen | Pull-to-refresh list, filter by status, search by job number/title |
| JobcardDetailScreen | Full jobcard info, status update button, time entry list, map button |
| TimeEntryFormScreen | Form to create/edit time entry |
| SettingsScreen | Logout button, biometric toggle, notification toggle |

---

## 12. SAMPLE API RESPONSES

### Login Response
```json
{
    "token": "2|abcdef123456",
    "token_type": "Bearer",
    "user": {
        "id": 5,
        "name": "Jane Technician",
        "email": "jane@company.com",
        "user_type": "standard"
    },
    "abilities": {
        "jobcards": ["list", "view", "edit"],
        "time-entries": ["list", "create", "edit"]
    }
}
```

### Jobcard List Response
```json
{
    "data": [
        {
            "id": 42,
            "job_number": "JC-2026-0042",
            "title": "Emergency: No AC",
            "description": "Customer reported complete AC failure...",
            "status": "dispatched",
            "priority": "emergency",
            "scheduled_start_at": "2026-05-12T08:00:00Z",
            "scheduled_end_at": "2026-05-12T10:00:00Z",
            "estimated_duration_minutes": 120,
            "service_address": "456 Oak Avenue, Suite 100, Los Angeles, CA 90001",
            "phone": "+1-555-0123",
            "email": "customer@email.com",
            "customer": {
                "id": 12,
                "name": "ABC Corporation",
                "phone": "+1-555-0123"
            },
            "assigned_to_user": {
                "id": 5,
                "name": "Jane Technician"
            },
            "assigned_to_team": null,
            "subtotal": "450.00",
            "tax_amount": "36.00",
            "total": "486.00",
            "line_items": [
                {
                    "id": 101,
                    "description": "Compressor replacement",
                    "quantity": 1,
                    "unit_price": "400.00",
                    "total": "400.00"
                }
            ],
            "created_at": "2026-05-10T14:30:00Z",
            "updated_at": "2026-05-12T07:45:00Z"
        }
    ],
    "links": {
        "first": "http://localhost:8000/api/v1/jobcards?page=1",
        "last": "http://localhost:8000/api/v1/jobcards?page=5",
        "prev": null,
        "next": "http://localhost:8000/api/v1/jobcards?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 5,
        "per_page": 20,
        "to": 20,
        "total": 95
    }
}
```

---

## 13. YOUR TASK

Generate a complete Swift mobile application with the following structure:

```
JobCardMobile/
├── Sources/
│   ├── App/
│   │   ├── JobCardMobileApp.swift
│   │   └── AppDelegate.swift
│   ├── Models/
│   │   ├── User.swift
│   │   ├── Jobcard.swift
│   │   ├── TimeEntry.swift
│   │   ├── Customer.swift
│   │   └── LocationPing.swift
│   ├── Services/
│   │   ├── APIService.swift
│   │   ├── AuthService.swift
│   │   ├── LocationService.swift
│   │   ├── NotificationService.swift
│   │   └── BiometricService.swift
│   ├── ViewModels/
│   │   ├── LoginViewModel.swift
│   │   ├── JobcardListViewModel.swift
│   │   ├── JobcardDetailViewModel.swift
│   │   └── TimeEntryViewModel.swift
│   ├── Views/
│   │   ├── LoginView.swift
│   │   ├── JobcardListView.swift
│   │   ├── JobcardDetailView.swift
│   │   ├── TimeEntryFormView.swift
│   │   ├── Components/
│   │   │   ├── StatusBadge.swift
│   │   │   ├── JobcardCard.swift
│   │   │   └── LoadingView.swift
│   │   └── SettingsView.swift
│   ├── Utilities/
│   │   ├── Constants.swift
│   │   ├── NetworkMonitor.swift
│   │   └── OfflineQueue.swift
│   └── Resources/
│       └── Assets.xcassets/
├── project.yml (XcodeGen)
├── Package.swift (SPM dependencies)
└── Info.plist
```

Key requirements:
1. Use Swift 6 and async/await for all API calls
2. Implement biometric login using `LocalAuthentication` (iOS)
3. Implement location tracking with `CoreLocation` (iOS)
4. Handle push notification registration with APNs
5. Support offline mode with basic caching
6. Use MVVM architecture with Combine
7. Store tokens securely in Keychain
8. Support all jobcard statuses and allow status transitions
9. Use SwiftUI for all views

IMPORTANT: Since we're using Swift SDK for Android, ALL SwiftUI code must be platform-agnostic. Do NOT use any iOS-only APIs in the Views layer. Keep platform-specific code in Services only.

Also add:
- Build configuration for both iOS and Android targets
- Proper error handling with user-friendly messages
- Loading states and empty states for all screens
```