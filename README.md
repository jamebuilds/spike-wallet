# Spike: Identity Wallet (OID4VCI / OID4VP)

A spike implementation of a web-based identity wallet supporting the **OpenID for Verifiable Credentials Issuance (OID4VCI)** and **OpenID for Verifiable Presentations (OID4VP)** standards.

The purpose of this spike is to validate the end-to-end credential lifecycle (receive, store, present) and inform architectural decisions for a production implementation.

## Table of Contents

- [What This Spike Covers](#what-this-spike-covers)
- [OID4VCI: Credential Issuance Flow](#oid4vci-credential-issuance-flow)
- [OID4VP: Credential Presentation Flow](#oid4vp-credential-presentation-flow)
- [Holder Key Management](#holder-key-management)
- [SD-JWT Parsing](#sd-jwt-parsing)
- [Credential Storage Schema](#credential-storage-schema)
- [Architecture & Design Decisions](#architecture--design-decisions)
- [Production Considerations](#production-considerations)

---

## What This Spike Covers

1. **Credential Issuance (OID4VCI)** - Receive credentials from issuers using pre-authorized code flow
2. **Credential Presentation (OID4VP)** - Present credentials to verifiers with selective disclosure
3. **Credential Management** - Store, view, and delete credentials
4. **Holder Key Binding** - EC P-256 key pair generation for proof of possession and key binding JWTs
5. **SD-JWT Support** - Parse and handle SD-JWT-VC (selective disclosure) and plain JWT-VC credentials
6. **QR Code Scanning** - Scan credential offer and authorization request URLs via camera

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

### Data Contracts

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

### Data Contracts

- `AuthorizationRequestDto` - client_id, response_uri, response_type, nonce, state, presentation_definition, response_mode

---

## Holder Key Management

- Generates **EC P-256** (NIST) key pairs on first use (lazy initialization — not at registration)
- Public key stored as **JWK** (JSON Web Key)
- Private key stored as PEM, **encrypted at rest**
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

## Credential Storage Schema

### User Key Columns

| Column | Type | Description |
|--------|------|-------------|
| `wallet_public_jwk` | json, nullable | EC P-256 public key in JWK format |
| `wallet_private_jwk` | text, nullable | EC P-256 private key (encrypted at rest) |

### Credentials Table

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

---

## Architecture & Design Decisions

### Service-per-step Pattern

Each step of the OID4VCI/OID4VP flow is its own service class (e.g., `CredentialOfferParser`, `TokenEndpointService`). The protocols are multi-step flows involving different external endpoints — isolating each step makes them independently testable, swappable, and easier to reason about. In production, this makes it straightforward to add retry logic, circuit breakers, or caching at specific steps.

### Typed DTOs for External API Data

All data received from external APIs (issuers, verifiers) is mapped into typed DTOs before use. External API responses are untrusted — DTOs provide a validation boundary, prevent accidental use of raw/unexpected fields, and give the codebase clear contracts for what data each flow step produces and consumes.

### Single Credential Model for Both Formats

Both SD-JWT-VC and plain JWT-VC credentials are stored in the same table, differentiated by a `format` column. The storage and display requirements are nearly identical. A `format` column is sufficient to branch behavior at the service layer (e.g., selective disclosure only applies to `vc+sd-jwt`).

### Holder Keys on User (not separate table)

For this spike, each user has exactly one key pair stored directly on the users table. In production, consider a dedicated `holder_keys` table for multiple keys or key rotation.

### UUID Primary Keys for Credentials

Credentials are sensitive. UUIDs prevent enumeration attacks and are suitable for distributed systems if the credential store is ever sharded or replicated.

### Lazy Key Generation

Holder key pairs are generated on first credential issuance, not at registration. Not all users will use wallet features — lazy generation avoids unnecessary overhead and simplifies registration.

### Private Key Encryption at Rest

Private keys are stored as PEM text, encrypted at rest using the application encryption key (AES-256-CBC). Transparently decrypted when accessed. In production, consider HSM or cloud KMS integration.

### Client-side QR Code Scanning

Credential offer and authorization request URLs are embedded in QR codes by issuers/verifiers. Client-side scanning provides instant feedback without uploading images to the server.

---

## Production Considerations

Areas to address when moving from spike to production:

### Security
- [ ] Move private key storage to **HSM or cloud KMS** (AWS KMS, Azure Key Vault, etc.)
- [ ] Add **key rotation** support with a dedicated `holder_keys` table
- [ ] Implement **credential revocation** checking (status list, revocation endpoints)
- [ ] Add **rate limiting** on issuance and presentation endpoints
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
- [ ] Add **queue-based processing** for external API calls (issuance, presentation)
- [ ] Implement **audit logging** for compliance
- [ ] Add **monitoring and alerting** for external API failures
- [ ] Consider **mobile app** (native or PWA) with a dedicated API layer

### Testing
- [ ] Add **integration tests** with mock issuers and verifiers
- [ ] Add **end-to-end tests** for full issuance and presentation flows
- [ ] Add **conformance testing** against OID4VCI/OID4VP test suites
