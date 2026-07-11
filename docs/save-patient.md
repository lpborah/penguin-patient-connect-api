# savePatient — Flow Documentation

**Endpoint:** `POST /savePatient`  
**Controller:** `App\Controllers\PatientController::savePatient()`

---

## Request Payload

| Field | Required | Type | Description |
|---|---|---|---|
| `customer_id` | ✅ | int | Customer the patient belongs to |
| `patient_name` | ✅ | string | Full name of the patient |
| `mobile` | ✅ | string | Mobile number (raw — normalized internally) |
| `first_name` | ❌ | string | First name (used in WhatsApp template) |
| `last_name` | ❌ | string | Last name (used in WhatsApp template) |
| `external_patient_id` | ❌ | string | HIS/external system patient ID |
| `age` | ❌ | string | Patient age |
| `sex` | ❌ | string | Patient gender |
| `consent_source` | ❌ | string | Source of consent initiation |
| `visit_date` | ❌ | date `Y-m-d` | Defaults to today if omitted |
| `external_visit_id` | ❌ | string | HIS/external visit ID |
| `source_type` | ❌ | string | e.g. `HIS`, `MANUAL`, `CSV` |
| `source_reference` | ❌ | string | Source reference string |
| `department` | ❌ | string | Department name |
| `doctor_name` | ❌ | string | Attending doctor |
| `laboratory_id` | ❌ | string | Lab identifier |
| `bill_number` | ❌ | string | Bill / invoice number |
| `bill_amount` | ❌ | decimal | Bill amount |
| `admission_number` | ❌ | string | Admission number |
| `ward` | ❌ | string | Ward name |
| `bed` | ❌ | string | Bed identifier |

---

## Flow

### Step 1 — Input Validation
- Sanitizes all request fields via `Validator::sanitizeString()`
- Validates required fields: `customer_id`, `patient_name`, `mobile`
- Returns `400` if any required field is missing or `customer_id` is non-integer

---

### Step 2 — Duplicate / Existing Patient Check
Queries `patient_master` for the given `customer_id` and tries to find a match:

1. **By mobile** — checks three variants: raw, normalized (`+91XXXXXXXXXX`), normalized without `+`
2. **By `external_patient_id`** — fallback lookup if mobile didn't match

---

### Step 3 — Existing Patient Branch
> Triggered when a matching patient is found in Step 2

- Queries `patient_consents` for the latest record with `consent_status = PENDING` for that patient
- **No pending consent found** → returns `200` with message `"mobile number already exist"` (consent already completed or never pending — nothing to resend)
- **Pending consent found →**
  1. Generates a fresh raw token (`bin2hex(random_bytes(32))`)
  2. Marks old `ACTIVE` tokens for that `consent_id` as `SUPERSEDED` in `consent_tokens`
  3. Inserts new token row in `consent_tokens` with fresh expiry (`+7 days`)
  4. Calls **AiSensy API** → sends WhatsApp consent message with new token URL
  5. Calls `insertVisitRecord()` → saves row in `patient_visits`
  6. Calls `insertPatientMessage()` → logs the outbound message in `patient_messages`
  7. Returns `200` success *(see Response section)*

---

### Step 4 — New Patient Branch
> Triggered when no existing patient is found

Runs inside a **DB transaction** (rolls back on failure):

| # | Action | Table |
|---|---|---|
| 1 | Insert patient record | `patient_master` |
| 2 | Insert consent record with `consent_status = PENDING` | `patient_consents` |
| 3 | Generate SHA-256 token hash + insert token | `consent_tokens` |

After `commit()` (outside transaction — failures are non-fatal):

| # | Action |
|---|---|
| 4 | Call **AiSensy API** → send WhatsApp consent message |
| 5 | `insertVisitRecord()` → save row in `patient_visits` |
| 6 | `insertPatientMessage()` → log outbound message in `patient_messages` |

Returns `200` success *(see Response section)*

---

## AiSensy API Call (`callAiSensyApi`)

- **Endpoint:** `POST https://backend.aisensy.com/campaign/t1/api/v2`
- **Campaign:** `HM Consent V1`
- **Auth:** `Authorization: Bearer <AISENSY_API_KEY>` (from `.env`)
- **Payload fields:**

| Field | Value |
|---|---|
| `campaignName` | `HM Consent V1` |
| `destination` | Normalized mobile (`+91XXXXXXXXXX`) |
| `userName` | `first_name` (fallback: `patient_name`, then `"User"`) |
| `templateParams` | `[firstName, lastName]` |
| `buttonUrlParam` | `https://ppc.penguinhealth.com/consent/accept?t=<rawToken>` |
| `apiKey` | `AISENSY_API_KEY` env var |

- Response is stored in `patient_messages.provider_response`
- `submitted_message_id` from response is stored as `provider_message_id`

---

## Tables Written

| Table | When |
|---|---|
| `patient_master` | New patient only |
| `patient_consents` | New patient only |
| `consent_tokens` | Both branches (new token always created) |
| `patient_visits` | Both branches (always) |
| `patient_messages` | Both branches (always — non-fatal) |

---

## Success Response

```json
{
  "status": "success",
  "message": "Patient created successfully",
  "data": {
    "patient_id": 42,
    "consent_id": 7,
    "consent_token_id": 15,
    "consent_token": "<raw_token>",
    "expires_at": "2026-07-18 10:30:00",
    "visit_id": 3,
    "aisensy_response": { ... }
  }
}
```

> For the existing-patient resend branch, `message` is `"Consent message resent to existing patient"`.

---

## Error Responses

| HTTP | Message | Condition |
|---|---|---|
| `400` | `Missing required fields: ...` | Required field absent |
| `400` | `Invalid customer ID` | Non-integer `customer_id` |
| `200` | `mobile number already exist` | Patient exists, no pending consent |
| `500` | `Failed to save patient` | Unhandled DB exception |

---

## Error Handling Notes

- The DB transaction (Steps 1–3 of new patient) rolls back on any `\Exception`
- AiSensy API failure, `insertVisitRecord` failure, and `insertPatientMessage` failure are all **non-fatal** — caught internally, logged via `AppLogger`, and do not cause a `500` response
