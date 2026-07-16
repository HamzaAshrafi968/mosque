# Task 1 — Add `is_synced` to all Model `$fillable` + `$casts`

## Summary

كل موديل في الجداول المتأثرة يجب أن يضيف `is_synced` إلى `$fillable` (للـ mass assignment) و `$casts` (للتحويل إلى boolean). بدون هذا التغيير، أي طلب API يحتوي على `is_synced` لن يتم حفظه.

---

## Models to modify (18 files)

### Models with `MultiTenantTrait` + `UuidTrait`

| # | File | `$fillable` already has? | `$casts` already has? |
|---|------|--------------------------|-----------------------|
| 1 | `app/Models/Patient.php` | No | No |
| 2 | `app/Models/Appointment.php` | No | No |
| 3 | `app/Models/TreatmentSession.php` | No | No |
| 4 | `app/Models/SessionTreatment.php` | No | No |
| 5 | `app/Models/PatientAssessment.php` | No | No |
| 6 | `app/Models/Invoice.php` | No | No |
| 7 | `app/Models/InvoiceItem.php` | No | No |
| 8 | `app/Models/Payment.php` | No | No |
| 9 | `app/Models/Expense.php` | No | No |
| 10 | `app/Models/Medication.php` | No | No |
| 11 | `app/Models/TreatmentType.php` | No | No |
| 12 | `app/Models/ClinicSetting.php` | No | No |
| 13 | `app/Models/WorkingHour.php` | No | No |
| 14 | `app/Models/WorkingHourRange.php` | No | No |
| 15 | `app/Models/User.php` | No | No |

### Models without `MultiTenantTrait` (unscoped)

| # | File | `$fillable` already has? |
|---|------|--------------------------|
| 16 | `app/Models/Doctor.php` | No |
| 17 | `app/Models/Prescription.php` | No |
| 18 | `app/Models/WhatsappMessage.php` | No |

### Already done

| File | Status |
|------|--------|
| `app/Models/Notification.php` | Already has `is_synced` in both |
| `app/Models/Attachment.php` | Check if exists |

---

## Change per file

For each model, add `'is_synced'` to the `$fillable` array and `'is_synced' => 'boolean'` to the `$casts` array:

```php
protected $fillable = [
    // ... existing fields
    'is_synced',
];

protected $casts = [
    // ... existing casts
    'is_synced' => 'boolean',
];
```

### Example — `Patient.php`

```php
// Before:
protected $fillable = [
    'id',
    'tenant_id',
    'fullName',
    // ...
];

protected $casts = [
    'id' => 'string',
    'date_of_birth' => 'datetime:Y-m-d',
    'tenant_id' => 'string',
];

// After:
protected $fillable = [
    'id',
    'tenant_id',
    'fullName',
    // ...
    'is_synced',
];

protected $casts = [
    'id' => 'string',
    'date_of_birth' => 'datetime:Y-m-d',
    'tenant_id' => 'string',
    'is_synced' => 'boolean',
];
```

---

## Notes

- **لا تلمس `$hidden`** — `is_synced` يجب أن يكون مرئيًا في API responses حتى يعرف الموبايل حالة المزامنة.
- **لا تلمس `$guarded`** — إن وُجد، ضف `is_synced` إلى `$fillable` بدلًا (أفضل صراحة).
- `Doctor` model غير scoped لكنه يشارك في المزامنة (doctors table scoped by tenant_id كـ FK).
