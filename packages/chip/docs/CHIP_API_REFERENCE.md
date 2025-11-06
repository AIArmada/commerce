# CHIP API Reference

**Last updated:** October 2025  
**Sources:** [CHIP Collect Docs](https://docs.chip-in.asia/chip-collect/) • [CHIP Send Docs](https://docs.chip-in.asia/chip-send/)

This reference distils the official documentation into a format optimised for day-to-day development with the `aiarmada/chip` Laravel package. Every endpoint listed below is available in production and verified against the public documentation as of October 2025.

---

## Table of Contents

1. [Service Overview](#service-overview)  
2. [Base URLs & Authentication](#base-urls--authentication)  
3. [Common Conventions](#common-conventions)  
4. [CHIP Collect API](#chip-collect-api)  
    - [Purchases](#purchases)  
    - [Payment Methods](#payment-methods)  
    - [Clients](#clients)  
    - [Webhooks](#collect-webhooks)  
    - [Public Key](#public-key)  
    - [Account](#account)  
    - [Company Statements](#company-statements)  
5. [CHIP Send API](#chip-send-api)  
    - [Accounts](#accounts)  
    - [Bank Accounts](#bank-accounts)  
    - [Send Instructions](#send-instructions)  
    - [Groups](#groups)  
    - [Webhooks](#send-webhooks)  
6. [Webhook Events](#webhook-events)  
7. [Reference Models](#reference-models)  
8. [Testing Notes](#testing-notes)

---

## Service Overview

| Service       | Purpose                                   |
|---------------|-------------------------------------------|
| CHIP Collect  | E-commerce payments, invoices, subscriptions, refunds |
| CHIP Send     | Disbursements, vendor payouts, internal transfers |

Both services share the same authentication style (Bearer tokens) but live on different base URLs.

---

## Base URLs & Authentication

### Base URLs

| Service      | Environment | Base URL                                      |
|--------------|-------------|-----------------------------------------------|
| **CHIP Collect** | All         | `https://gate.chip-in.asia/api/v1/`           |
|              |             | *Uses same URL for sandbox/production. API key determines environment.* |
| **CHIP Send**    | Sandbox     | `https://staging-api.chip-in.asia/api/`       |
|              | Production  | `https://api.chip-in.asia/api/`               |

### Authentication

#### CHIP Collect
```http
Authorization: Bearer {API_KEY}
```
The API key determines sandbox vs production environment.

#### CHIP Send
```http
Authorization: Bearer {API_KEY}
epoch: {unix_timestamp}
checksum: {hmac_signature}
```

**Required Headers:**
- `epoch` – Current Unix timestamp (seconds)
- `checksum` – `hash_hmac('sha256', (string) $epoch, API_SECRET)`

#### Required Headers (Both Services)
```http
Accept: application/json
Content-Type: application/json
```

> **Note:** CHIP does not emit HTTP 429 for rate limiting. Handle 5xx responses appropriately.

---

## Common Conventions

- Monetary amounts are integers in **sen** (MYR cents). Convert to major units for display.  
- Identifiers are opaque strings prefixed with resource types (`pur_`, `cli_`, etc.).  
- Timestamps are Unix epoch seconds unless the field name ends with `_at` (ISO8601).  
- Boolean query flags use `true` / `false` (JSON boolean).  
- Optional fields should be omitted entirely rather than sent as empty strings.

---

## CHIP Collect API

### Purchases

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/purchases/` | `POST` | Create a purchase (payment link, invoice, or direct post). Requires either `client` payload with email or `client_id`. Must include `brand_id`, `purchase.products` (name, price). Optional fields include redirects, metadata, pre-auth flags. |
| `/purchases/{id}/` | `GET` | Retrieve full purchase details, including client, payment, transaction data, history. |
| `/purchases/{id}/cancel/` | `POST` | Cancel an unpaid purchase. |
| `/purchases/{id}/refund/` | `POST` | Refund a paid purchase. Body may include `amount` for partial refunds. |
| `/purchases/{id}/capture/` | `POST` | Capture a previously authorised payment. Optional `amount`. |
| `/purchases/{id}/release/` | `POST` | Release a pre-authorised hold. |
| `/purchases/{id}/mark_as_paid/` | `POST` | Mark an offline payment as paid. Optional `paid_on` (epoch seconds). |
| `/purchases/{id}/resend_invoice/` | `POST` | Resend payment link / invoice email to customer. |
| `/purchases/{id}/charge/` | `POST` | Charge using a saved `recurring_token`. Body: `recurring_token`. |
| `/purchases/{id}/recurring_token/` | `DELETE` | Permanently delete the recurring token associated with the purchase. |

**Key Response Fields**

- `status`: `created`, `paid`, `cancelled`, `refunded`, etc.  
- `purchase.total`: integer total in cents.  
- `payment`: populated when a transaction is recorded.  
- `issuer_details`, `transaction_data`: information about the merchant and payment attempts.  
- `checkout_url` / `direct_post_url`: customer payment entry points.

### Payment Methods

- `GET /payment_methods/` – Returns enabled payment methods for the merchant brand. Supports filters like `currency`, `payment_method`.

### Clients

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/clients/` | `POST` | Create a saved client/customer. |
| `/clients/` | `GET` | List clients. Supports pagination using query params. |
| `/clients/{id}/` | `GET` | Retrieve client profile. |
| `/clients/{id}/` | `PUT` | Replace client data. |
| `/clients/{id}/` | `PATCH` | Partially update client data. |
| `/clients/{id}/` | `DELETE` | Delete a client. |
| `/clients/{id}/recurring_tokens/` | `GET` | List saved recurring tokens. |
| `/clients/{id}/recurring_tokens/{token}/` | `GET` | Retrieve a specific recurring token. |
| `/clients/{id}/recurring_tokens/{token}/` | `DELETE` | Remove a recurring token. |

### Collect Webhooks

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/webhooks/` | `POST` | Create a webhook subscription. |
| `/webhooks/{id}/` | `GET` | Retrieve webhook details. |
| `/webhooks/{id}/` | `PUT` | Update webhook configuration. |
| `/webhooks/{id}/` | `DELETE` | Delete webhook subscription. |
| `/webhooks/` | `GET` | List webhook subscriptions. |

Webhook payloads are signed using the RSA public key retrievable from `/public_key/`. Always verify signatures using the raw request body and the configured public key.

### Public Key

- `GET /public_key/` – Returns PEM public key used to validate webhook signatures and success callbacks.

### Account

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/account/balance/` | `GET` | Retrieve merchant balance and currency totals. |
| `/account/turnover/` | `GET` | Retrieve turnover report. Supports query params like `start_date`, `end_date`. |

### Company Statements

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/company_statements/` | `GET` | List statements. Supports filters (`status`, `period`). |
| `/company_statements/{id}/` | `GET` | Retrieve a specific statement. |
| `/company_statements/{id}/cancel/` | `POST` | Cancel a queued statement. |

---

## CHIP Send API

### Accounts

- `GET /send/accounts` – List payout accounts linked to the merchant.

### Bank Accounts

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/send/bank_accounts` | `POST` | Create a bank account to be used as a payout beneficiary. Requires `bank_code`, `account_number`, `name`. |
| `/send/bank_accounts/{id}` | `GET` | Retrieve bank account details. |
| `/send/bank_accounts` | `GET` | List bank accounts. Supports filters (`status`, `group_id`). |
| `/send/bank_accounts/{id}` | `PUT` | Update bank account metadata (e.g., `reference`). |
| `/send/bank_accounts/{id}` | `DELETE` | Delete bank account. |
| `/send/bank_accounts/{id}/resend_webhook` | `POST` | Re-send the most recent webhook for the bank account. |

### Send Instructions

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/send/send_instructions` | `POST` | Create a disbursement. Body must include `bank_account_id`, `amount` (string major currency, e.g. `"100.00"`), `currency`, `description`, `reference`, `email`. |
| `/send/send_instructions/{id}` | `GET` | Retrieve a send instruction. |
| `/send/send_instructions` | `GET` | List send instructions with filters (`state`, `created_after`, etc.). |
| `/send/send_instructions/{id}/cancel` | `POST` | Cancel a pending send instruction. |
| `/send/send_instructions/{id}/delete` | `DELETE` | Delete a send instruction (rare). |
| `/send/send_instructions/{id}/resend_webhook` | `POST` | Re-send the webhook notification for the instruction. |

Instruction states include `received`, `enquiring`, `executing`, `reviewing`, `accepted`, `completed`, `rejected`, `deleted`.

### Groups

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/send/groups` | `POST` | Create a group for organising bank accounts. |
| `/send/groups/{id}` | `GET` | Retrieve group details. |
| `/send/groups/{id}` | `PUT` | Update group name / metadata. |
| `/send/groups/{id}` | `DELETE` | Delete a group. |
| `/send/groups` | `GET` | List groups. |

### Send Webhooks

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/send/webhooks` | `POST` | Create a CHIP Send webhook subscription. |
| `/send/webhooks/{id}` | `GET` | Retrieve webhook configuration. |
| `/send/webhooks/{id}` | `PUT` | Update webhook settings. |
| `/send/webhooks/{id}` | `DELETE` | Delete webhook subscription. |
| `/send/webhooks` | `GET` | List webhooks. |

Webhooks notify about bank account status changes, budget allocation updates, and send instruction progress. Payload signatures follow the same RSA mechanism as Collect.

---

## Webhook Events

Common Collect events:

| Event | Description |
|-------|-------------|
| `purchase.created` | Purchase record created. |
| `purchase.paid` | Payment completed. |
| `purchase.cancelled` | Purchase cancelled. |
| `payment.created` | Payment attempt created. |
| `payment.paid` | Payment confirmed. |
| `payment.failed` | Payment attempt failed. |

Send events reported via webhooks include `bank_account_status`, `budget_allocation_status`, and `send_instruction_status` with state transitions described above.

Always verify signatures using the public key and the literal request body. Reject and log any payload failing verification.

---

## Reference Models

- **Purchase**: contains `client`, `purchase`, `payment`, `issuer_details`, `transaction_data`, `status_history`, `checkout_url`.  
- **Client**: personal/contact information plus optional legal details (company name, registration number).  
- **Payment**: `is_outgoing`, `payment_type`, `amount`, `net_amount`, `fee_amount`, `pending_amount`, optional timestamps.  
- **SendInstruction**: `id`, `bank_account_id`, `amount` (string), `state`, `email`, `description`, `reference`, `receipt_url`, timestamps.  
- **BankAccount**: `status` (`pending`, `verified`, `rejected`), capabilities (`is_debiting_account`, `is_crediting_account`), audit timestamps.

---

## Testing Notes

- Sandbox card for Collect: `4444 3333 2222 1111`, CVC `123`, expiry any future month/year (per docs).  
- Test send instructions with small amounts; CHIP transitions through the documented states automatically in sandbox.  
- When writing automated tests, prefer the package’s data objects (`Purchase`, `Payment`, `SendInstruction`, etc.) to assert behaviour rather than raw arrays.

---

For deeper details (error codes, enum values, Direct Post flows), consult the official CHIP documentation linked at the top of this file.
