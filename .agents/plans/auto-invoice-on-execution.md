# Auto-Invoice on Treatment Session Execution

## Summary
Treatment sessions with `status = 'scheduled'` (planned stage) are hidden from accounts/invoices. When a session transitions to `in_progress` (execution), an invoice is auto-generated with all session and treatment prices. One invoice per session.

---

## Task 1 — Fix TreatmentSession status constants

**File:** `app/Models/TreatmentSession.php:42-46`

Replace mismatched constants with values matching the DB enum:

```php
const STATUS_SCHEDULED   = 'scheduled';
const STATUS_IN_PROGRESS = 'in_progress';
const STATUS_COMPLETED   = 'completed';
const STATUS_CANCELLED   = 'cancelled';
```

---

## Task 2 — Add treatment_session_id to invoices

**File:** new migration

Add nullable `treatment_session_id` FK to `invoices` table:

```php
$table->string('treatment_session_id')->nullable()->after('patient_id');
$table->foreign('treatment_session_id')->references('id')->on('treatment_sessions')->onDelete('set null');
```

Update `Invoice` model `$fillable` to include `'treatment_session_id'`.

---

## Task 3 — Create BillingService

**File:** `app/Services/BillingService.php` (new)

```php
class BillingService
{
    public function generateInvoiceForSession(TreatmentSession $session): Invoice
}
```

Responsibilities:
- Guard: skip if `Invoice::where('treatment_session_id', $session->id)->exists()`
- Generate invoice number (reuse prefix from `ClinicSetting`)
- Create `Invoice` with `treatment_session_id` linked
- Create `InvoiceItem` rows:
  - Session-level `price` (if > 0) as one line item
  - Each `SessionTreatment` price as a line item, linked via `session_treatment_id`
- Wrap in `DB::transaction`

---

## Task 4 — Inject billing into TreatmentSessionController::update()

**File:** `app/Http/Controllers/Api/TreatmentSessionController.php` ~line 325-329

After the `$treatmentSession->update($updateData)` call:

```php
if (($oldStatus ?? null) !== 'in_progress' && ($updateData['status'] ?? null) === 'in_progress') {
    $billingService = app(BillingService::class);
    $invoice = $billingService->generateInvoiceForSession($treatmentSession);
    // include $invoice in response
}
```

Store the old status before the update to compare.

---

## Task 5 — Exclude planned sessions from patient financials

**File:** `app/Models/Patient.php`

Three methods affected:

| Method | Line | Change |
|--------|------|--------|
| `getTotalRequired()` | 117-122 | Add `$query->where('status', '!=', 'scheduled')` |
| `getFinancialSummary()` | 134-153 | Same subquery filter |
| `getTreatmentsWithPrices()` | 155-160 | Same subquery filter |

Methods NOT changed (already correct):
- `getTotalBalance()` — uses invoices, planned sessions have none

---

## Task 6 — Add invoice relationship to TreatmentSession

**File:** `app/Models/TreatmentSession.php`

```php
public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(Invoice::class, 'treatment_session_id');
}
```

---

## Edge Cases

| Scenario | Handled by |
|---|---|
| `scheduled -> in_progress` | Creates invoice |
| `in_progress -> scheduled` (back) | Guard prevents duplicate |
| `in_progress -> cancelled` | Invoice stays, can be manually cancelled |
| `completed -> completed` (no-op) | No trigger, no duplicate |
| Session has $0 price + no treatments | Invoice created with $0 total |

---

## Files Checklist

- [ ] `app/Models/TreatmentSession.php` — fix constants, add `invoice()` relation
- [ ] `database/migrations/...add_treatment_session_id_to_invoices.php` — new
- [ ] `app/Models/Invoice.php` — add `treatment_session_id` to fillable
- [ ] `app/Services/BillingService.php` — new
- [ ] `app/Http/Controllers/Api/TreatmentSessionController.php` — inject billing
- [ ] `app/Models/Patient.php` — filter scheduled from financials
