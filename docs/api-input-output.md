# API Input / Output Reference

This document summarizes the routes, controllers, API request payloads / query parameters and response shapes, plus the MySQL tables and relationships inferred from the codebase in this repository.

**NOTE**: This is generated from the current code (controllers and routes). Validate against your live DB schema for exact column names (some controllers reference `tr_upload_path` vs `image_upload_path`, `receipt_no` vs legacy `recript_no`, etc.).

**Table of contents**
- Controllers & Routes
- API endpoints: Inputs & Outputs
- MySQL tables and relationships
- Important behavior notes
- Recommended next steps

**Controllers & Routes**

- `/` : GET — health check

- MessageController
  - `GET /getMessages` — list contact messages
  - `POST /updateMessage` — update message status
  - `POST /saveMessage` — create contact message
  - `POST /contactUs` — save + send contact email

- ResourceController
  - `GET /getResources`
  - `GET /resources/{id}`
  - `POST /saveResource`
  - `POST /updateResource`
  - `POST /deleteResource`

- ClientController
  - `GET /getClients`
  - `POST /saveClient`
  - `POST /updateClient`
  - `POST /deleteClient` (soft delete)
  - `POST /importClients` (CSV file)
  - `GET /getClientById` (?client_id=)

- LeadsController
  - `GET /getLeads`
  - `POST /saveLead`
  - `POST /updateLead`
  - `POST /deleteLead` (soft delete)
  - `POST /importLeads` (CSV)
  - `GET /getLeadById` (?lead_id=)

- UserController
  - `GET /getUsers`
  - `POST /saveUser`
  - `POST /updateUser`
  - `POST /deleteUser` (soft delete)
  - `POST /restoreDeletedUser`
  - `GET /getRoles`

- AuthController
  - `POST /login` — expects `username`, `password`
  - `POST /updatePassword`
  - `POST /logout`

- ClientSubscriptionsController
  - `GET /getClientSubscriptions`
  - `POST /saveClientSubscription`
  - `POST /updateClientSubscription`
  - `POST /deleteClientSubscription`
  - `GET /getSubscriptions`
  - `GET /getClientSubscriptionsById` (?client_id=)

- SubscriptionController
  - `GET /getAllSubscriptions`
  - `POST /importSubscriptions` (CSV)
  - `POST /saveSubscription`
  - `POST /updateSubscription`
  - `POST /deleteSubscription`

- QuotationController
  - `GET /getQuotations`
  - `GET /getQuotationById` (?id=)
  - `POST /saveQuotation` (master + details + terms)
  - `POST /updateQuotation`
  - `POST /deleteQuotation`
  - `GET /getHsnMaster`
  - `GET /getTermsMaster`
  - `POST /saveQuotationDetails`

- RentalInvoiceController
  - `GET /getRentalInvoices`
  - `GET /getRentalInvoiceById` (?id=)
  - `POST /saveRentalInvoice` (master + details)
  - `POST /updateRentalInvoice`
  - `POST /deleteRentalInvoice`
  - `POST /saveRentalInvoiceDetails`
  - `GET /getClientsWithSubscriptions`
  - `GET /getClientSubscriptions` (?client_id=)

- PaymentController
  - `POST /savePayment`
  - `GET /getAllPayments`
  - `GET /getPaymentsByYearMonth` (?month=&year=)
  - `GET /getPaymentDetailsByInvoiceNo` (?invoice_no=)
  - `POST /uploadTransactionReceipt` (multipart)
  - `GET /getPaymentDetailsById` (?id=)


**API endpoints: Inputs & Outputs**

Below are the commonly-used endpoints and the expected request payloads / query parameters and response shapes as used by the controllers.

- `POST /savePayment`
  - Required payload (form/json):
    - `invoice_no` (string)
    - `received_amount` (number)
  - Optional: `invoice_amount`, `channel`, `reference_no`, `received_by`, `remark`, `user`
  - Behavior: sums previous payments for invoice, computes balance, inserts row into `payment_details`, generates `receipt_no`, updates `invoice_master.status`.
  - Response (success):
    {
      "status": "Paid" | "Partial Payment" | "Pending",
      "insert_id": <int>,
      "receipt_no": "<prefix>/<fy>/<serial>",
      "invoice_no": "...",
      "received_amount": <float>,
      "received_by": "..." | null,
      "created_at": "YYYY-MM-DD HH:MM:SS"
    }

- `GET /getAllPayments`
  - Query params: optional `user`
  - Response: array of payment objects with fields such as `id`, `invoice_no`, `receipt_no`, `invoice_amount`, `received_amount` (or `current_received` in some code paths), `balance_amount`, `received_date`, `payment_channel`, `reference_no`, `received_by`, upload path column(s), `remark`, `user`, `created_at`, plus invoice_master fields (`client_id`, `month`, `year`, `status`).

- `GET /getPaymentsByYearMonth?month=&year=`
  - Response: same shape as above filtered by `received_date`.

- `GET /getPaymentDetailsByInvoiceNo?invoice_no=`
  - Response: the most recent payment record for the invoice (fields similar to single payment object).

- `GET /getPaymentDetailsById?id=`
  - Response: single payment with fields:
    - `id`, `invoice_no`, `receipt_no`, `invoice_amount`, `current_received` (received_amount), `balance_amount`, `received_date`, `payment_channel`, `reference_no`, `received_by`, `tr_upload_path`, `remark`, `user`, `created_at`.

- `POST /uploadTransactionReceipt` (multipart/form-data)
  - Fields: `invoice_no`, `file` (UploadedFile), optional `user`
  - Accepts: `image/jpeg`, `image/png`, `application/pdf`
  - Saves to: `uploads/transaction-receipts/` and updates `payment_details.tr_upload_path` with relative path `uploads/transaction-receipts/<filename>`.
  - Response: `{ "file_path": "uploads/transaction-receipts/<filename>" }`

Other endpoints follow similar patterns: validate required fields, perform DB query/update, return `{...}` or error via `ApiResponse::error`.


**MySQL tables and relationships (inferred)**

- `users` — columns used: `user_id` (PK), `user_name`, `email`, `password`, `phone_number`, `role_id`, `status`, `created_at`

- `roles` — `role_id`, `role_name`

- `clients` — `client_id` (PK), `lead_id`, `client_name`, `primary_contact_name`, `primary_contact_phone`, `primary_contact_email`, `billing_address`, `city`, `state`, `country`, `pincode`, `gst_number`, `status`, `created_by`, `updated_by`, `created_at`

- `leads` — `lead_id`, `company_name`, `contact_person`, `contact_email`, `contact_phone`, `lead_source`, `status`, `notes`, `created_at`

- `contact_messages` — `id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `ip_address`, `created_at`
- `resources` — `id`, `category`, `resourceName`, `url`, `created_at`
- `subscription_master` — `id`, `subscription_name`, `description`, `billing_type`, `default_billing_cycle`, `is_amc_prompt_required`, `created_at`
- `client_subscriptions` — `id`, `client_id`(FK→clients.client_id), `subscription_master_id`(FK→subscription_master.id), `negotiated_price`, `billing_cycle`, `status`, `start_date`, `next_invoice_date`, `amc_required`, `amc_date`, `amc_cost`, `created_at`
- `invoice_master` — `id`, `invoice_no`, `client_id`(FK→clients.client_id), `invoice_type`, `invoice_for_month`, `invoice_for_year`, `description`, `invoice_amount`, `discount`, `cgst_percent`, `sgst_percent`, `cgst_value`, `sgst_value`, `total_invoice_amount`, `status`, `created_by`, `updated_by`, `created_at`
- `invoice_details` — `id`, `invoice_no`(FK→invoice_master.invoice_no), `subscription_id`/`subscription_master_id`, `rate`, `quantity`, `user`, `created_by`, `updated_by`
- `invoice_terms` — `id`, `invoice_no`, `term_text`, `created_at`
- `quotation_master` — `id`, `quote_no`, `lead_id`(FK→leads.lead_id), `client_id`(FK→clients.client_id), `subtotal`, `cgst_percent`, `sgst_percent`, `cgst_value`, `sgst_value`, `total_gst`, `total_amount`, `status`, `created_by`, `updated_by`, `created_at`
- `quotation_details` — `id`, `quote_no`(FK→quotation_master.quote_no), `hsn_code`, `rate`, `quantity`, `item_description`, `amount`, `user`, `created_by`, `updated_by`
- `quotation_terms` — `id`, `quote_no`, `term_text`, `user`, `created_by`, `updated_by`, `created_at`
- `hsn_master` — `hsn_code`, `description`
- `payment_details` — `id`, `invoice_no`(FK→invoice_master.invoice_no), `receipt_no`, `invoice_amount`, `received_amount`, `balance_amount`, `received_date`, `payment_channel`, `reference_no`, `received_by`, `remark`, `user`, `created_by`, `updated_by`, `created_at`, `tr_upload_path`, `image_upload_path`

Primary relationships (inferred):
- `clients.client_id` ← `client_subscriptions.client_id`, `invoice_master.client_id`, `quotation_master.client_id`
- `subscription_master.id` ← `client_subscriptions.subscription_master_id`, `invoice_details.subscription_id`
- `invoice_master.invoice_no` ← `invoice_details.invoice_no`, `payment_details.invoice_no`
- `quotation_master.quote_no` ← `quotation_details.quote_no`, `quotation_terms.quote_no`
- `leads.lead_id` ← `quotation_master.lead_id`


**Important behavior notes & edge cases**

- `savePayment` computes cumulative received amount by summing `received_amount` from `payment_details` for the invoice, then inserts the new payment and sets invoice status (`Pending`, `Partial Payment`, `Paid`).
- `receipt_no` generation rules:
  - prefix: `GST` if channel contains `bank`, `MR` if contains `cash`, else first 3 letters of channel uppercase or `RN` fallback.
  - fiscal year string derived from current date (Apr–Mar fiscal year logic).
  - serial number: uses MAX of last part of existing receipt_no and increments; starts at ≥ 101.
- File uploads: `uploadTransactionReceipt` stores files under repo `uploads/transaction-receipts/` and DB stores a relative path `uploads/transaction-receipts/{filename}` in `payment_details.tr_upload_path`.
- Several controllers perform soft deletes by setting `status` fields rather than deleting rows.
- CSV import endpoints expect specific headers. See controller import logic for exact required columns.
- Code references several column names that may not match your DB exactly (e.g., `tr_upload_path` vs `image_upload_path`, `receipt_no` vs `recript_no`). Confirm schema before deploying.



