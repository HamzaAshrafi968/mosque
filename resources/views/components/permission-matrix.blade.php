@props([
    'mode' => 'role',
    'baseline' => [],
    'overrides' => [],
    'granted' => [],
    'exclude' => ['mosques'],
    'copySources' => null,
])

@php
    $resourceLabels = \App\Support\PermissionCatalog::resourceLabels();
    $grouped = collect(\App\Support\PermissionCatalog::grouped())
        ->reject(fn ($permissions, $resource) => in_array($resource, $exclude, true))
        ->all();

    $isUser = $mode === 'user';
    $totalRows = collect($grouped)->flatten(1)->count();
    $scopeLabels = ['mosque' => 'الجامع الخاص', 'own' => 'خاص بالمستخدم', 'class' => 'صفوف محددة', 'section' => 'شعب محددة'];
@endphp

<div data-permission-matrix data-mode="{{ $mode }}" class="flex flex-col">
    {{-- شريط الأدوات: بحث + إجراءات جماعية + عدادات --}}
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/60">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-56">
                <input type="search" data-permission-search placeholder="ابحث عن صلاحية (الاسم أو الرمز)…"
                       class="w-full border border-gray-300 rounded-lg ps-9 pe-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                <svg class="absolute start-3 top-2.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34m1.35-5.58A7.5 7.5 0 1 1 3.6 11.1a7.5 7.5 0 0 1 16.1-.01Z"/></svg>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($isUser)
                    <button type="button" data-permission-global-action="inherit"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">وراثة الكل</button>
                    <button type="button" data-permission-global-action="mosque"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition">منح الكل للجامع</button>
                @else
                    <button type="button" data-permission-global-action="mosque"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition">منح الكل للجامع</button>
                    <button type="button" data-permission-global-action="revoke"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition">رفض الكل</button>
                @endif

                @if(!$isUser && $copySources instanceof \Illuminate\Support\Collection && $copySources->isNotEmpty())
                    <select data-permission-copy class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                        <option value="">نسخ من دور آخر…</option>
                        @foreach($copySources as $source)
                            <option value="{{ $source->id }}" data-grants='@json($source->permissions->mapWithKeys(fn ($permission) => [$permission->code => $permission->pivot->scope]))'>{{ $source->name }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="button" data-permission-collapse-all
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">طي الكل</button>
                <button type="button" data-permission-expand-all
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-gray-100 text-gray-700 hover:bg-gray-200 transition">فتح الكل</button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-gray-500">
            <span>{{ $totalRows }} عملية</span>
            <span class="text-gray-300">•</span>
            <span data-permission-counter></span>
        </div>
    </div>

    {{-- المجموعات --}}
    @foreach($grouped as $resource => $permissions)
        <div class="border-b border-gray-100 last:border-0" data-permission-group data-group-label="{{ $resourceLabels[$resource] ?? $resource }}">
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50">
                <button type="button" data-permission-group-toggle aria-expanded="true" class="flex items-center gap-2 font-bold text-gray-700 text-sm">
                    <svg data-permission-group-chevron class="h-4 w-4 text-gray-400 transition-transform rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    <span>{{ $resourceLabels[$resource] ?? $resource }}</span>
                    <span class="text-xs font-normal text-gray-400" data-group-count>({{ count($permissions) }})</span>
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" data-permission-group-action="mosque"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition">منح الكل</button>
                    <button type="button" data-permission-group-action="{{ $isUser ? 'inherit' : 'revoke' }}"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition">{{ $isUser ? 'وراثة الكل' : 'رفض الكل' }}</button>
                </div>
            </div>

            <div data-permission-group-panel>
                <table class="w-full">
                    @foreach($permissions as $permission)
                        @php
                            $code = $permission['code'];

                            if ($isUser) {
                                $base = $baseline[$code] ?? [];
                                $current = $overrides[$code] ?? 'inherit';
                            } else {
                                $current = $granted[$code] ?? '';
                            }
                        @endphp
                        <tr class="border-t border-gray-50" data-permission-row data-code="{{ $code }}" data-label="{{ $permission['label'] }}">
                            <td class="px-5 py-2.5 w-1/2 align-top">
                                <div class="flex items-center gap-2 flex-wrap" data-permission-label-wrap>
                                    <span class="text-sm text-gray-700">{{ $permission['label'] }}</span>
                                    @if($isUser && $current !== 'inherit')
                                        <span data-override-badge class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $current === 'deny' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">تجاوز</span>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-300 font-mono" dir="ltr">{{ $code }}</span>
                                @if($isUser)
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        نطاق الدور:
                                        @if($base === [])
                                            <span class="text-red-400">مرفوض</span>
                                        @else
                                            @foreach($base as $scope)
                                                <span class="inline-block px-1.5 rounded bg-gray-100 text-gray-600">{{ \App\Enums\RoleScope::tryFrom($scope)?->label() ?? $scope }}</span>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 align-top">
                                <select name="permissions[{{ $code }}]" data-permission-select class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-full md:w-56">
                                    @if($isUser)
                                        <option value="inherit" @selected($current === 'inherit')>— وراثة الدور —</option>
                                        <option value="deny" @selected($current === 'deny') @disabled($base === []) title="{{ $base === [] ? 'الدور لا يمنح هذه الصلاحية أصلاً' : '' }}">منع صريح</option>
                                    @else
                                        <option value="" @selected($current === '')>— مرفوض —</option>
                                    @endif
                                    @foreach($scopeLabels as $scope => $label)
                                        <option value="{{ $scope }}" @selected($current === $scope)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @endforeach
</div>
