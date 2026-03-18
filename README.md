# Spike: Identity Wallet (OID4VCI / OID4VP)

A spike implementation of a web-based identity wallet supporting the **OpenID for Verifiable Credentials Issuance (OID4VCI)** and **OpenID for Verifiable Presentations (OID4VP)** standards.

The purpose of this spike is to validate the end-to-end credential lifecycle (receive, store, present) and inform architectural decisions for a production implementation.

## Table of Contents

- [What This Spike Covers](#what-this-spike-covers)
- [Tech Stack](#tech-stack)
- [Architecture Overview](#architecture-overview)
- [Database Schema](#database-schema)
- [OID4VCI: Credential Issuance Flow](#oid4vci-credential-issuance-flow)
- [OID4VP: Credential Presentation Flow](#oid4vp-credential-presentation-flow)
- [Holder Key Management](#holder-key-management)
- [SD-JWT Parsing](#sd-jwt-parsing)
- [Routes](#routes)
- [Project Structure](#project-structure)
- [Design Decisions & Rationale](#design-decisions--rationale)
- [Production Considerations](#production-considerations)
- [Setup](#setup)

---

## What This Spike Covers

1. **Credential Issuance (OID4VCI)** - Receive credentials from issuers using pre-authorized code flow
2. **Credential Presentation (OID4VP)** - Present credentials to verifiers with selective disclosure
3. **Credential Management** - Store, view, and delete credentials
4. **Holder Key Binding** - EC P-256 key pair generation for proof of possession and key binding JWTs
5. **SD-JWT Support** - Parse and handle SD-JWT-VC (selective disclosure) and plain JWT-VC credentials
6. **QR Code Scanning** - Scan credential offer and authorization request URLs via camera

---

## Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend Framework | Laravel | 12 |
| PHP | | 8.4 |
| Frontend Framework | React | 19 |
| Server-Client Bridge | Inertia.js | v2 |
| Styling | Tailwind CSS | v4 |
| JWT Library | lcobucci/jwt | v5 |
| Auth Backend | Laravel Fortify | v1 |
| Route Binding (TS) | Laravel Wayfinder | v0 |
| Testing | Pest | v4 |
| Build Tool | Vite | v7 |
| Database | SQLite | (configurable) |
| UI Components | Radix UI | various |
| QR Scanner | html5-qrcode | v2.3 |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                   Frontend (React)                  │
│  ┌──────────┐  ┌───────────┐  ┌──────────────────┐ │
│  │  Wallet   │  │ Issuance  │  │  Authorization   │ │
│  │ Dashboard │  │  Accept   │  │  Consent Screen  │ │
│  └──────────┘  └───────────┘  └──────────────────┘ │
│         │             │               │             │
│         └─────────────┼───────────────┘             │
│                       │ Inertia.js                  │
├───────────────────────┼─────────────────────────────┤
│                   Backend (Laravel)                 │
│  ┌────────────────────┼──────────────────────────┐  │
│  │              Controllers (Wallet/)            │  │
│  │  Dashboard │ Issuance │ Authorization │ Show  │  │
│  └────────────┼──────────┼───────────────┼───────┘  │
│               │          │               │          │
│  ┌────────────▼──┐  ┌────▼────────────┐  │         │
│  │  OID4VCI      │  │  OID4VP         │  │         │
│  │  Services     │  │  Services       │  │         │
│  │               │  │                 │  │         │
│  │ OfferParser   │  │ AuthReqParser   │  │         │
│  │ MetadataRes.  │  │ PDefMatcher     │  │         │
│  │ TokenEndpoint │  │ SdJwtPresenter  │  │         │
│  │ PoPBuilder    │  │ VpTokenResponse │  │         │
│  │ CredEndpoint  │  │                 │  │         │
│  │ CredStorage   │  │                 │  │         │
│  └───────────────┘  └─────────────────┘  │         │
│                                          │         │
│  ┌──────────────┐  ┌─────────────────┐   │         │
│  │ HolderKey    │  │  SD-JWT Parser  │   │         │
│  │ Service      │  │  (Token, Disc.) │   │         │
│  └──────────────┘  └─────────────────┘   │         │
│                                          │         │
│  ┌──────────────┐  ┌─────────────────┐   │         │
│  │   DTOs       │  │   Policies      │───┘         │
│  │ (Oid4vci/vp) │  │ (Authorization) │             │
│  └──────────────┘  └─────────────────┘             │
│                                                     │
│  ┌──────────────────────────────────────┐           │
│  │         Models (User, SdJwtCred)     │           │
│  └──────────────────────────────────────┘           │
└─────────────────────────────────────────────────────┘
```

### Layer Breakdown

- **Controllers** (`app/Http/Controllers/Wallet/`) - Thin HTTP layer. Parse requests, delegate to services, return Inertia responses.
- **Services** (`app/Service/Oid4vci/`, `app/Service/Oid4vp/`) - All protocol logic lives here. Each service handles one step of the OID4VCI or OID4VP flow.
- **DTOs** (`app/Dto/`) - Typed data transfer objects for external API communication. Prevent accidental data leakage and provide clear contracts.
- **Policies** (`app/Policies/`) - Authorization rules (users can only access their own credentials).
- **Models** (`app/Models/`) - Eloquent models with relationships (`User` hasMany `SdJwtCredential`).

---

## Database Schema

### Users Table (wallet-specific columns)

| Column | Type | Description |
|--------|------|-------------|
| `wallet_public_jwk` | json, nullable | EC P-256 public key in JWK format |
| `wallet_private_jwk` | text, nullable | EC P-256 private key (encrypted at rest via Laravel's `encrypted` cast) |

### SD JWT Credentials Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid (PK) | Unique credential identifier |
| `user_id` | foreignId | Owner (cascades on delete) |
| `raw_sd_jwt` | text | Full raw credential string |
| `issuer_claims` | json | All claims from the issuer JWT payload |
| `disclosed_claims` | json | Claims available for selective disclosure |
| `issuer` | string | Issuer URI |
| `vct` | string, nullable | Verifiable Credential Type identifier |
| `format` | string | `vc+sd-jwt` or `jwt_vc_json` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## OID4VCI: Credential Issuance Flow

The wallet receives credentials from issuers using the **pre-authorized code** grant type.

```
User scans QR / pastes credential_offer URL
         │
         ▼
┌─────────────────────┐
│ CredentialOfferParser│  Parse offer URL → extract issuer, config IDs, pre-auth code
└────────┬────────────┘
         │
         ▼
┌─────────────────────┐
│IssuerMetadataResolver│  GET /.well-known/openid-credential-issuer
└────────┬────────────┘   Fallback: /.well-known/oauth-authorization-server
         │                → token_endpoint, credential_endpoint, supported configs
         ▼
┌─────────────────────┐
│ TokenEndpointService │  POST token_endpoint (pre-authorized_code grant)
└────────┬────────────┘   → access_token, c_nonce
         │
         ▼
┌─────────────────────┐
│ProofOfPossessionBldr │  Build PoP JWT (signed with holder private key)
└────────┬────────────┘   Header: { alg: ES256, typ: openid4vci-proof+jwt, jwk: <public> }
         │                Payload: { iss, aud, iat, nonce: c_nonce }
         ▼
┌─────────────────────┐
│CredentialEndpointSvc │  POST credential_endpoint (Bearer access_token + proof JWT)
└────────┬────────────┘   → raw credential (SD-JWT or JWT)
         │
         ▼
┌─────────────────────┐
│CredentialStorageSvc  │  Parse credential → detect format → extract claims → store
└─────────────────────┘
```

### Implementation Notes

- **Credential offer** can contain either an inline offer object or a `credential_offer_uri` that must be fetched
- **Metadata resolution** tries the OID4VCI well-known endpoint first, then falls back to OAuth server metadata
- **PoP JWT** embeds the holder's public key in the JWT header (`jwk` claim) for holder binding
- **Format detection**: if the credential contains `~` separators, it's SD-JWT; otherwise plain JWT
- **SD-JWT parsing**: splits on `~` to get issuer JWT + disclosures, decodes each disclosure's base64url JSON

### Key DTOs

- `CredentialOfferDto` - credential_issuer, credential_configuration_ids, pre_authorized_code, tx_code
- `IssuerMetadataDto` - credential_issuer, token_endpoint, credential_endpoint, credential_configurations_supported
- `TokenResponseDto` - access_token, c_nonce, expires_in
- `CredentialResponseDto` - credential (raw string), c_nonce

---

## OID4VP: Credential Presentation Flow

The wallet presents credentials to verifiers in response to authorization requests.

```
User scans QR / pastes authorization request URL
         │
         ▼
┌──────────────────────────┐
│AuthorizationRequestParser │  Parse URL → extract client_id, response_uri, nonce,
└────────┬─────────────────┘   presentation_definition (inline or via URI/request_uri JWT)
         │
         ▼
┌──────────────────────────┐
│PresentationDefMatcher     │  Match user's credentials against presentation requirements
└────────┬─────────────────┘   Filter by vct/type patterns → return matching credentials
         │                     with requested + available claims
         ▼
    User selects credential
    & chooses claims to disclose
         │
         ▼
┌──────────────────────────┐
│ SdJwtPresenter           │  Build presentation token:
└────────┬─────────────────┘   SD-JWT: issuer_jwt ~ selected_disclosures ~ kb_jwt
         │                     Plain JWT: wrap in VP Token envelope
         ▼
┌──────────────────────────┐
│ VpTokenResponseService   │  POST response_uri (vp_token + presentation_submission)
└──────────────────────────┘
```

### Implementation Notes

- **Authorization request** supports three ways to pass the presentation definition:
  1. Inline `presentation_definition` query parameter
  2. `presentation_definition_uri` to fetch remotely
  3. `request_uri` pointing to a JWT containing the definition
- **Credential matching** filters by `vct` field patterns in input descriptor constraints
- **Selective disclosure**: for SD-JWT credentials, users choose which claims to disclose; the presenter includes only the corresponding disclosure values
- **Key Binding JWT** (KB-JWT): signed with the holder's private key, contains `aud` (verifier), `nonce`, and `iat`
- **Plain JWT credentials**: wrapped in a VP Token envelope since they don't support selective disclosure
- **Response mode**: `direct_post` - the VP token is POSTed directly to the verifier's `response_uri`

### Key DTOs

- `AuthorizationRequestDto` - client_id, response_uri, response_type, nonce, state, presentation_definition, response_mode

---

## Holder Key Management

**Service**: `HolderKeyService`

- Generates **EC P-256** (NIST) key pairs on first use (lazy initialization)
- Public key stored as **JWK** (JSON Web Key) in the `wallet_public_jwk` column
- Private key stored as PEM, **encrypted at rest** using Laravel's `encrypted` cast
- Keys are used for:
  - **Proof of Possession** during credential issuance (PoP JWT in credential request)
  - **Key Binding JWT** during credential presentation (proves holder possession)
  - **VP Token signing** for plain JWT credentials

### Why EC P-256?

- Required by the OID4VCI/OID4VP specs for holder key binding
- Compact signatures suitable for JWTs
- Widely supported across JWT libraries

---

## SD-JWT Parsing

**Classes**: `SdJwtParser`, `SdJwtToken`, `Disclosure`, `Base64Url`

### SD-JWT Structure

```
<issuer-jwt> ~ <disclosure-1> ~ <disclosure-2> ~ ... ~ <kb-jwt>
```

- **Issuer JWT**: standard JWT with an `_sd` array containing hashes of disclosures
- **Disclosures**: base64url-encoded JSON arrays `[salt, claim_name, claim_value]`
- **KB-JWT** (optional): key binding JWT added during presentation

### Parsing Steps

1. Split credential string on `~` separator
2. First segment = issuer JWT (decode header + payload)
3. Middle segments = disclosures (base64url decode → JSON parse → extract claim name/value)
4. Last segment (if present at presentation time) = KB-JWT

### Format Detection

- Contains `_sd` in payload OR has `~` separated disclosures → `vc+sd-jwt`
- Otherwise → `jwt_vc_json` (plain W3C JWT, no selective disclosure)

---

## Routes

### Wallet Routes (authenticated + verified)

| Method | URI | Controller | Description |
|--------|-----|-----------|-------------|
| GET | `/wallet` | `WalletDashboardController` | List credentials, QR scan inputs |
| GET | `/wallet/receive` | `WalletCredentialIssuanceController::create` | Parse credential offer, show acceptance UI |
| POST | `/wallet/receive` | `WalletCredentialIssuanceController::store` | Execute issuance flow, store credential |
| GET | `/wallet/authorize` | `WalletAuthorizationController::create` | Parse auth request, show consent screen |
| POST | `/wallet/authorize` | `WalletAuthorizationController::store` | Submit presentation to verifier |
| GET | `/wallet/{sdJwtCredential}` | `WalletCredentialShowController` | View credential details (policy: `view`) |
| DELETE | `/wallet/{sdJwtCredential}` | `WalletCredentialDeleteController` | Delete credential (policy: `delete`) |

### Authentication Routes (Laravel Fortify)

Login, registration, password reset, email verification, and 2FA are all handled by Fortify's built-in routes.

---

## Project Structure

```
app/
├── Dto/
│   ├── Oid4vci/
│   │   ├── CredentialOfferDto.php
│   │   ├── CredentialResponseDto.php
│   │   ├── IssuerMetadataDto.php
│   │   └── TokenResponseDto.php
│   └── Oid4vp/
│       └── AuthorizationRequestDto.php
├── Http/
│   ├── Controllers/
│   │   ├── Settings/          # Profile, password, 2FA
│   │   └── Wallet/            # 5 wallet controllers
│   └── Requests/              # Form request validation
├── Models/
│   ├── User.php               # Extended with wallet key columns
│   └── SdJwtCredential.php    # UUID primary key, belongs to User
├── Policies/
│   └── SdJwtCredentialPolicy.php
└── Service/
    ├── Oid4vci/
    │   ├── CredentialEndpointService.php
    │   ├── CredentialOfferParser.php
    │   ├── CredentialStorageService.php
    │   ├── IssuerMetadataResolver.php
    │   ├── ProofOfPossessionBuilder.php
    │   └── TokenEndpointService.php
    └── Oid4vp/
        ├── AuthorizationRequest/
        │   ├── AuthorizationRequestParser.php
        │   └── PresentationDefinitionMatcher.php
        ├── SdJwt/
        │   ├── Base64Url.php
        │   ├── Disclosure.php
        │   ├── SdJwtParser.php
        │   ├── SdJwtPresenter.php
        │   └── SdJwtToken.php
        ├── HolderKeyService.php
        └── VpTokenResponseService.php

resources/js/pages/
├── wallet/
│   ├── index.tsx              # Dashboard with credential list + QR scanners
│   ├── show.tsx               # Credential detail view
│   ├── issuance/create.tsx    # Accept credential offer
│   └── authorization/create.tsx  # Consent screen for presentation
├── auth/                      # Login, register, 2FA, etc.
├── settings/                  # Profile, password, appearance
├── dashboard.tsx
└── welcome.tsx
```

---

## Design Decisions & Rationale

### 1. Service-per-step Architecture

**Decision**: Each step of the OID4VCI/OID4VP flow is its own service class (e.g., `CredentialOfferParser`, `TokenEndpointService`).

**Rationale**: The OID4VCI and OID4VP protocols are multi-step flows involving different external endpoints. Isolating each step makes individual steps independently testable, swappable, and easier to reason about. In production, this also makes it straightforward to add retry logic, circuit breakers, or caching at specific steps.

### 2. DTOs for External API Data

**Decision**: All data received from external APIs (issuers, verifiers) is mapped into typed DTOs before use.

**Rationale**: External API responses are untrusted. DTOs provide a validation boundary, prevent accidental use of raw/unexpected fields, and give the codebase clear contracts for what data each flow step produces and consumes.

### 3. Holder Keys on User Model (not separate table)

**Decision**: Store `wallet_public_jwk` and `wallet_private_jwk` directly on the `users` table.

**Rationale**: For this spike, each user has exactly one key pair. A separate table would add complexity without benefit. In production, consider a dedicated `holder_keys` table if users need multiple keys or key rotation.

### 4. SD-JWT Credential as Single Model

**Decision**: Both SD-JWT-VC and plain JWT-VC credentials are stored in the same `sd_jwt_credentials` table, differentiated by a `format` column.

**Rationale**: The storage and display requirements are nearly identical. A `format` column is sufficient to branch behavior at the service layer (e.g., selective disclosure only applies to `vc+sd-jwt`). Avoids model/table proliferation for what is fundamentally the same entity.

### 5. Private Key Encryption via Laravel Cast

**Decision**: Private keys are stored as PEM text encrypted using Laravel's `encrypted` cast.

**Rationale**: Leverages Laravel's built-in encryption (AES-256-CBC with the APP_KEY) without adding infrastructure. The private key is transparently decrypted when accessed on the model. In production, consider HSM or cloud KMS integration.

### 6. SQLite for Spike

**Decision**: Use SQLite as the default database.

**Rationale**: Zero configuration, file-based, ideal for rapid prototyping. The schema is simple enough that migration to MySQL/PostgreSQL requires no changes beyond the `.env` configuration.

### 7. Inertia.js (No Separate API)

**Decision**: Use Inertia.js for the frontend-backend bridge instead of a REST/GraphQL API.

**Rationale**: The wallet UI is tightly coupled to the backend flows. Inertia eliminates API boilerplate, provides type-safe props via Wayfinder, and keeps the full-stack in a single deployable unit. In production, a separate API layer may be needed if mobile clients are added.

### 8. UUID Primary Keys for Credentials

**Decision**: `sd_jwt_credentials` uses UUID primary keys instead of auto-incrementing integers.

**Rationale**: Credentials are sensitive. UUIDs prevent enumeration attacks and are suitable for distributed systems if the credential store is ever sharded or replicated.

### 9. QR Code Scanning on Frontend

**Decision**: QR scanning is handled client-side using `html5-qrcode` rather than server-side image processing.

**Rationale**: Credential offer and authorization request URLs are embedded in QR codes by issuers/verifiers. Client-side scanning provides instant feedback and doesn't require uploading images to the server.

### 10. Lazy Key Generation

**Decision**: Holder key pairs are generated on first credential issuance, not at registration.

**Rationale**: Not all registered users will use wallet features. Lazy generation avoids unnecessary key generation overhead and simplifies the registration flow.

---

## Production Considerations

Areas to address when moving from spike to production:

### Security
- [ ] Move private key storage to **HSM or cloud KMS** (AWS KMS, Azure Key Vault, etc.) instead of database encryption
- [ ] Add **key rotation** support with a dedicated `holder_keys` table
- [ ] Implement **credential revocation** checking (status list, revocation endpoints)
- [ ] Add **rate limiting** on issuance and presentation endpoints
- [ ] Implement **CSRF protection** for authorization flows
- [ ] Add **input validation** for credential offer/authorization request URLs before processing

### Protocol Compliance
- [ ] Support **authorization code** grant type (not just pre-authorized code) for OID4VCI
- [ ] Support **additional credential formats** (e.g., mDoc/mDL, W3C JSON-LD)
- [ ] Implement **credential metadata display** (issuer trust, credential schema info)
- [ ] Add **DPoP** (Demonstrating Proof of Possession) token support
- [ ] Handle **deferred credential issuance** (polling for credential readiness)

### User Experience
- [ ] Add **credential expiry** tracking and notifications
- [ ] Implement **credential refresh/re-issuance** flows
- [ ] Add **multi-credential presentation** support (presenting from multiple credentials in one flow)
- [ ] Improve **error handling** with user-friendly messages for protocol failures
- [ ] Add **activity log** for credential usage history

### Infrastructure
- [ ] Switch to **MySQL/PostgreSQL** for production workloads
- [ ] Add **queue-based processing** for external API calls (issuance, presentation)
- [ ] Implement **audit logging** for compliance
- [ ] Add **monitoring and alerting** for external API failures
- [ ] Consider **mobile app** (native or PWA) with a dedicated API layer

### Testing
- [ ] Add **integration tests** with mock issuers and verifiers
- [ ] Add **end-to-end tests** for full issuance and presentation flows
- [ ] Add **conformance testing** against OID4VCI/OID4VP test suites

---

## Setup

```bash
# Clone and install
git clone <repo-url>
cd spikeWallt
composer run setup

# Development (runs Laravel server + Vite + queue + logs)
composer run dev

# Run tests
composer run test

# Lint PHP
vendor/bin/pint --dirty

# Lint JS
npm run lint:check

# Type check
npm run types:check
```
