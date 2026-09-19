@extends('layouts.app')

@section('title', $program->name)

@section('content')
<div class="max-w-6xl mx-auto space-y-6" dir="rtl">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0">
            <span class="w-4 h-4 rounded-full mt-2 shrink-0" style="background: {{ $program->color ?: $program->type->color() }}"></span>
            <div class="min-w-0">
                <h2 class="text-2xl font-extrabold text-gray-800 truncate">{{ $program->name }}</h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap text-[11px]">
                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-bold">{{ $program->type->label() }}</span>
                    <span class="font-mono text-gray-400">{{ $program->code }}</span>
                    <span @class([
                        'px-2 py-0.5 rounded-full font-bold',
                        'bg-emerald-100 text-emerald-800' => $program->is_active,
                        'bg-gray-100 text-gray-500' => ! $program->is_active,
                    ])>{{ $program->is_active ? 'مفعّل' : 'معطّل' }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($canSchedule)
                <a href="{{ route('admin.schedules.index', ['program_id' => $program->id]) }}"
                   class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold px-4 py-2 rounded-lg">جدول البرنامج</a>
            @endif
            @if($canEdit)
                <a href="{{ route('admin.programs.edit', $program) }}"
                   class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تعديل</a>
            @endif
            <a href="{{ route('admin.programs.index') }}" class="text-gray-500 text-sm hover:underline">العودة للقائمة</a>
        </div>
    </div>

    @if($program->description)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <p class="text-sm text-gray-600 leading-relaxed">{{ $program->description }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-2xl font-extrabold text-gray-800">{{ $program->periods_count }}</div>
            <div class="text-xs text-gray-500 mt-1">فترات</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-2xl font-extrabold text-gray-800">{{ $program->attributes_count }}</div>
            <div class="text-xs text-gray-500 mt-1">خصائص</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 text-center">
            <div class="text-2xl font-extrabold text-gray-800">{{ $program->schedules_count }}</div>
            <div class="text-xs text-gray-500 mt-1">حصص</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4">
            <div class="text-xs font-bold text-gray-500 mb-2">الدوامات</div>
            <div class="flex items-center gap-1 flex-wrap text-[11px]">
                @if($program->studySessions->isEmpty())
                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">كل الدوامات</span>
                @else
                    @foreach($program->studySessions as $session)
                        <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">{{ $session->name }}</span>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- الخصائص المخصصة --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="mb-4">
            <h3 class="font-extrabold text-gray-800">الخصائص المخصصة</h3>
            <p class="text-xs text-gray-500 mt-0.5">حقول هذا البرنامج وقيمها — تُدار من تعديل البرنامج</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-3 py-2 text-right">الاسم</th>
                        <th class="px-3 py-2 text-right">المفتاح</th>
                        <th class="px-3 py-2 text-right">النوع</th>
                        <th class="px-3 py-2 text-right">القيمة</th>
                        <th class="px-3 py-2 text-right">الخيارات</th>
                        <th class="px-3 py-2 text-center">مطلوبة</th>
                        <th class="px-3 py-2 text-right">الترتيب</th>
                        <th class="px-3 py-2 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attributeRows as $row)
                        @php $attribute = $row['model']; @endphp
                        <tr @class(['border-t border-gray-100', 'opacity-50' => ! $attribute->is_active])>
                            <td class="px-3 py-2.5 font-bold text-gray-800">{{ $attribute->name }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-500">{{ $attribute->field_key }}</td>
                            <td class="px-3 py-2.5 text-xs">{{ $attribute->field_type->label() }}</td>
                            <td class="px-3 py-2.5">
                                <span class="font-semibold text-gray-800">{{ $row['display'] }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-gray-600">
                                @if(empty($row['options']))
                                    <span class="text-gray-300">—</span>
                                @else
                                    @php
                                        $preview = array_slice($row['options'], 0, 3);
                                        $remaining = count($row['options']) - count($preview);
                                    @endphp
                                    <span title="{{ implode('، ', $row['options']) }}">
                                        {{ implode('، ', $preview) }}@if($remaining > 0) <span class="text-gray-400">(+{{ $remaining }})</span>@endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if($attribute->required)
                                    <span class="text-red-600 font-bold text-xs">نعم</span>
                                @else
                                    <span class="text-gray-400 text-xs">لا</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-gray-500">{{ $attribute->sort_order }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                    'bg-emerald-100 text-emerald-800' => $attribute->is_active,
                                    'bg-gray-100 text-gray-500' => ! $attribute->is_active,
                                ])>{{ $attribute->is_active ? 'مفعّلة' : 'معطّلة' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-10 text-center text-gray-400 text-sm">
                                لا توجد خصائص لهذا البرنامج بعد —
                                @if($canEdit)
                                    <a href="{{ route('admin.programs.edit', $program) }}" class="text-emerald-700 hover:underline">أضفها من تعديل البرنامج</a>
                                @else
                                    تُضاف من تعديل البرنامج
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- الفترات --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="mb-4">
            <h3 class="font-extrabold text-gray-800">الفترات</h3>
            <p class="text-xs text-gray-500 mt-0.5">فترات هذا البرنامج التي تُبنى عليها أوقات الحصص</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-3 py-2 text-right">اسم الفترة</th>
                        <th class="px-3 py-2 text-right">من</th>
                        <th class="px-3 py-2 text-right">إلى</th>
                        <th class="px-3 py-2 text-right">الترتيب</th>
                        <th class="px-3 py-2 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($program->periods as $period)
                        <tr @class(['border-t border-gray-100', 'opacity-50' => ! $period->is_active])>
                            <td class="px-3 py-2.5 font-bold text-gray-800">{{ $period->name }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-600">{{ $period->starts_at ? substr($period->starts_at, 0, 5) : '—' }}</td>
                            <td class="px-3 py-2.5 font-mono text-xs text-gray-600">{{ $period->ends_at ? substr($period->ends_at, 0, 5) : '—' }}</td>
                            <td class="px-3 py-2.5 text-xs text-gray-500">{{ $period->sort_order }}</td>
                            <td class="px-3 py-2.5 text-center">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                    'bg-emerald-100 text-emerald-800' => $period->is_active,
                                    'bg-gray-100 text-gray-500' => ! $period->is_active,
                                ])>{{ $period->is_active ? 'مفعّلة' : 'معطّلة' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-10 text-center text-gray-400 text-sm">لا توجد فترات لهذا البرنامج بعد</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
