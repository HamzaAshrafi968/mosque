# خطة التنفيذ (منقّحة QA) — Unified Teacher Tasmee (الزر الموحد «+ التسميع مع المعلم»)

## الحالة الحالية (مُتحقق منها)
- السجل الموحد منفّذ بالكامل: `QuranTeacherTimelineService` + `QuranTeacherTimelineItem` + `x-quran-teacher-timeline` + فلترة قبل الدمج + ترتيب حتمي + `tests/Feature/QuranTeacherTimelineTest.php`.
- المتبقي فقط: بوابة الدخول الموحدة (زر واحد + شاشة اختيار النوع + prefill) — §3–9، §19–20، §24.

## قرارات QA مؤكدة
- `AuthorizationService::can($user, perm, $student)` بلا predicate = عزل جامع فقط → آمن لفحص التوافر؛ العزل الفعلي للمعلم عبر `QuranScopeService::assertCanManageStudent`.
- `Route::has("admin.quran-review.create")` = false → المدير يرى نوعين فقط (§19: لا نخترع workflow).
- تسميات الـTimeline («استماع مع المعلم» في `QuranTeacherTimelineType::label()`) لا تتغير — اختبار التجويع يعتمد عليها؛ شاشة الاختيار تستخدم «استماع وتقييم تفصيلي».
- أذونات مُتحقق منها في `PermissionCatalog`: مدير = `quran.tasmee.create|quran_review.create` (mosque)، معلم = نفسها (own)، طالب = `quran_batch.view` فقط → middleware `canAny` يحجب الطالب/ولي بـ403.

## 1. ملفات جديدة
1. `app/Enums/QuranTeacherSessionType.php`: `NewRecitation='new'` / `Revision='revision'` / `Listening='listening'` + `label()` + `description()`.
2. `app/Support/QuranTeacherSessionTypeOption.php`: DTO readonly (`type`, `url`).
3. `app/Services/QuranTeacherSessionService.php`:
   - `typesFor(User $actor, Student $student, string $routePrefix): Collection`
   - new/revision → `route("$routePrefix.quran.tasmee.create", ['student_id'=>…, 'type'=>…])` إن `can(quran.tasmee.create, $student)`.
   - listening → `route("$routePrefix.quran-review.create", …)` إن `can(quran_review.create, $student)` **و** `Route::has(...)`.
   - بلا منطق حفظ.

## 2. Routes (بدون تعارض مسارات — لا GET `quran/batches/{x}`)
- Admin + Teacher: `GET quran/batches/session-start` → `QuranBatchController::sessionStart`، أسماء `*.quran.batches.session-start`، middleware `permission:quran.tasmee.create,quran_review.create` (OR عبر `EnsurePermission::canAny`).

## 3. Controllers
- `Admin\QuranBatchController`: حقن الخدمة؛ `sessionStart()`:
  - Validate: `student_id => required|uuid|exists:students,id` (QA: 422 بدل 404 غامض).
  - `currentBatch = $this->gating->currentBatch($student)` (سياق الدفعة في الشاشة).
  - عرض `quran.batches.session-type` مع `typesFor(..., 'admin.')`.
  - حذف `'sessionCreateRoute' => null` من `index()`.
- `Teacher\QuranBatchController`: نفس الشيء + `$this->scope->assertCanManageStudent($teacher, $student)` (QA: عزل المعلم)؛ حذف `sessionCreateRoute` من `index()`.

## 4. شاشة الاختيار `resources/views/quran/batches/session-type.blade.php` (مشتركة)
- عنوان «+ التسميع مع المعلم» + اسم الطالب + بطاقة سياق الدفعة الحالية إن وُجدت (label/status — مطابق لروح §5) + بطاقات الخيارات (روابط `<a>` بأسلوب Radio — GET بلا CSRF) + حالة فارغة دفاعية + زر رجوع `backUrl`.

## 5. توحيد الزر في `resources/views/quran/batches/index-panel.blade.php`
- حذف `$tasmeeCreateUrl`/`$sessionCreateUrl` → `$sessionStartUrl = $sessionStartRoute ?? null`.
- بطاقة الطالب العلوية: حذف «+ تسجيل تسميع» (إبقاء «فتح رحلة الطالب»).
- ترويسة قسم «التسميع مع المعلم»: زر واحد «+ التسميع مع المعلم»؛ حذف «+ جلسة استماع».
- الوصف الجديد: «متابعة الحفظ والأداء مع المعلم — تسميع حفظ جديد، مراجعة، أو استماع وتقييم تفصيلي من بوابة واحدة.»

## 6. Wrapper views (admin + teacher batches/index)
- استبدال `tasmeeCreateRoute` بـ`sessionStartRoute => fn ($student) => route('*.quran.batches.session-start', ['student_id' => $student->id])`.

## 7. Prefill النوع (QA: مع إصلاح فقدان type عند تغيير الطالب)
- `Admin\QuranTasmeeController::create` + `Teacher\QuranTasmeeController::create`: قراءة `request('type')` (يُقبل `new|revision` فقط) → تمرير `presetType`.
- `resources/views/admin/quran/tasmee/create.blade.php` (سطر 53) و`teacher/...` (سطر 53): `@selected(old('type', $presetType) === $type->value)`.
- **JS** (admin سطر 108 / teacher سطر 99): إبقاء `type` في رابط إعادة التحميل: `... + @json($presetType ? '&type='.$presetType : '')` — وإلا ضاع الـPreselect عند تبديل الطالب.

## 8. اختبارات (إضافة إلى `tests/Feature/QuranTeacherTimelineTest.php`)
1. `test_batches_center_exposes_single_unified_session_button` (admin): `assertSee('+ التسميع مع المعلم')` + `assertSee(route session-start)` + `assertDontSee('+ تسجيل تسميع')` + `assertDontSee('+ جلسة استماع')`.
2. `test_teacher_center_exposes_single_unified_session_button` (teacher — ربط الطالب بتسميع ليدخل نطاقه).
3. `test_teacher_chooser_offers_all_three_types_with_correct_urls` (روابط `type=new`/`type=revision`/`quran-review.create`).
4. `test_admin_chooser_hides_listening_and_shows_batch_context` (`assertDontSee('استماع وتقييم تفصيلي')` + assertSee label الدفعة).
5. `test_chooser_rejects_out_of_scope_student_for_teacher` (403).
6. `test_chooser_rejects_roles_without_permissions` (طالب وولي → 403).
7. `test_tasmee_create_preselects_session_type_from_query` (teacher: `type=new` → `value="new" selected="selected"` + بطاقة الدفعة؛ `type=revision` → نفسه للنوع).
- ملاحظة: لا اختبار لوصلات JS (سلوك متصفح) — بند Manual QA.

## 9. لن نلمس (§27)
- لا Models/Migrations/Coverage/Mastery/Gating/`listeningSessions` (مطلوبة لـ review-details/plan-details) ولا تسميات Timeline.

## 10. Manual QA checklist
- [ ] تبديل الطالب في نموذج التسميع بعد دخول `type=new` يبقي النوع.
- [ ] المدير لا يرى خيار الاستماع؛ المعلم يراه ويفتح `quran-review.create` بطالب محدد.
- [ ] فلاتر `timeline_type` و`status` لا تتعارض في رابط العودة من الشاشة.
- [ ] RTL: البطاقات والشعارات تظهر سليمة.

## 11. التحقق الآلي
1. `vendor/bin/pint --dirty`
2. `php artisan test` كامل (Regression: MemorizationBatchTest, TasmeeBatchIntegrationTest, KhamsaReviewTest, QuranListeningPlanTest, QuranTeacherTimelineTest, PermissionQaTest, TeacherQuranReviewPagesTest, Phase0RegressionTest, QuranProgramsTest, QuranPagesTest)
3. تحديث AGENTS.md بفقرة البوابة الموحدة.
