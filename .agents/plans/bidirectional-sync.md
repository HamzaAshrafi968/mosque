# Bidirectional Incremental Sync — Server Side

## Summary

تفعيل المزامنة ثنائية الاتجاه (mobile ↔ server) بشكل تزايدي باستخدام `is_synced` column الموجود في جميع الجداول.

**Current state:** `is_synced` column exists on all 20 tables via migration `2026_07_03_000001` but:
- Models don't expose it in `$fillable`/`$casts` (except `Notification`)
- No API endpoint supports `?is_sync=0` filtering
- No `sync_metadata` table exists
- No bulk push endpoint exists

**Goal:** Server can:
1. Return only unsynced records (`?is_sync=0`) per entity type
2. Mark returned records as `is_synced = true` after pull
3. Track last pull timestamps via `sync_metadata` table
4. Accept bulk pushes from mobile
5. Auto-set `is_synced = false` on server-side creates/updates (so mobile can pull them)

---

## Core Concepts

### `is_synced` flag meaning (server-side)

| Value | Meaning |
|-------|---------|
| `false` (default) | تم إنشاؤه/تعديله على السيرفر — لم يُسحب بعد إلى الموبايل |
| `true` | تم سحبه إلى الموبايل — أو تم دفعه من الموبايل إلى السيرفر |

### Sync flow

```
Server creates data (web UI / internal API) → is_synced = false
Mobile pull: GET /api/patients?is_sync=0  → returns patients where is_synced = false
Server marks: patients where is_synced = false → is_synced = true

Mobile creates data offline → stored in local sync_queue
Mobile push: POST /api/sync/bulk-push  → server upserts with is_synced = true
Mobile pull: GET /api/patients?is_sync=0  → does NOT return what mobile just pushed
```

---

All tasks: See details in individual files below.

### Files

| # | File | Description |
|---|------|-------------|
| — | `bidirectional-sync.md` | This overview (you are here) |
| 1 | `bidirectional-sync-01-models-is-synced.md` | Add `is_synced` to all model `$fillable` + `$casts` |
| 2 | `bidirectional-sync-02-sync-metadata.md` | Create `sync_metadata` table, model, controller |
| 3 | `bidirectional-sync-03-index-filter.md` | Add `?is_sync=0` filter + mark-as-synced to all index endpoints |
| 4 | `bidirectional-sync-04-store-update-flag.md` | Auto-set `is_synced = false` on server-side creates/updates |
| 5 | `bidirectional-sync-05-bulk-push.md` | Create bulk push endpoint for mobile → server data |
| 6 | `bidirectional-sync-06-routes.md` | Register all new routes in `routes/api.php` |

---

## Files Checklist

- [ ] 18 model files — add `is_synced` to `$fillable` + `$casts`
- [ ] `database/migrations/...create_sync_metadata_table.php` — new
- [ ] `app/Models/SyncMetadata.php` — new
- [ ] `app/Http/Controllers/Api/SyncController.php` — new
- [ ] `routes/api.php` — add sync routes
- [ ] 14 controller `index()` methods — add `?is_sync=0` filter + mark-as-synced
- [ ] 14 controller `store()`/`update()` methods — set `is_synced = false`
