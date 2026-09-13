@php $resourceLabels = \App\Support\PermissionCatalog::resourceLabels(); @endphp

@foreach(\App\Support\PermissionCatalog::grouped() as $resource => $permissions)
    <div class="border-b border-gray-100 last:border-0">
        <div class="px-5 py-3 bg-gray-50 font-bold text-gray-700 text-sm">{{ $resourceLabels[$resource] ?? $resource }}</div>
        <table class="w-full">
            @foreach($permissions as $permission)
                @php
                    $baseline = $roleScopes[$permission['code']] ?? [];
                    $current = $overrides[$permission['code']] ?? 'inherit';
                @endphp
                <tr class="border-t border-gray-50">
                    <td class="px-5 py-2.5 w-1/2">
                        <span class="text-sm text-gray-700">{{ $permission['label'] }}</span>
                        <span class="text-xs text-gray-300 mr-2 font-mono" dir="ltr">{{ $permission['code'] }}</span>
                        <div class="text-xs text-gray-400 mt-0.5">
                            صلاحية الدور:
                            @if($baseline === [])
                                <span>مرفوضة</span>
                            @else
                                @foreach($baseline as $scope)
                                    <span class="inline-block px-1.5 rounded bg-gray-100 text-gray-600">{{ \App\Enums\RoleScope::tryFrom($scope)?->label() ?? $scope }}</span>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td class="px-5 py-2.5">
                        <select name="permissions[{{ $permission['code'] }}]" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-full md:w-56">
                            <option value="inherit" @selected($current === 'inherit')>— وراثة الدور —</option>
                            <option value="deny" @selected($current === 'deny')>منع صريح</option>
                            <option value="mosque" @selected($current === 'mosque')>الجامع الخاص</option>
                            <option value="own" @selected($current === 'own')>خاص بالمستخدم</option>
                            <option value="class" @selected($current === 'class')>صفوف محددة</option>
                            <option value="section" @selected($current === 'section')>شعب محددة</option>
                        </select>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
