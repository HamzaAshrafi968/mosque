@props(['khamsat', 'selected' => []])

<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @foreach($khamsat as $juzRow)
        <div @class([
            'rounded-xl border p-3',
            'border-emerald-200 bg-emerald-50/50' => $juzRow['memorized'],
            'border-gray-200 bg-gray-50' => ! $juzRow['memorized'],
        ])>
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="font-bold text-gray-800">الجزء {{ $juzRow['juz'] }}</span>
                <span class="text-xs text-gray-500">صفحات {{ $juzRow['from_page'] }}–{{ $juzRow['to_page'] }}</span>
                @if($juzRow['memorized'])
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">محفوظ ✓</span>
                @else
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 font-bold">غير محفوظ — خمساته مقفلة</span>
                @endif
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                @foreach($juzRow['khamsat'] as $khamsa)
                    @php
                        $value = $juzRow['juz'].':'.$khamsa['khamsa'];
                        $checked = in_array($value, $selected, true);
                        $disabled = ! $khamsa['unlocked'] || $khamsa['already_assigned'];
                    @endphp
                    <label @class([
                        'flex flex-col rounded-lg border px-3 py-2 text-sm transition',
                        'border-emerald-300 bg-white hover:border-emerald-500 cursor-pointer' => ! $disabled,
                        'border-gray-200 bg-gray-100 text-gray-400 cursor-not-allowed' => $disabled,
                        'ring-2 ring-emerald-500 border-emerald-500' => $checked,
                    ])>
                        <span class="flex items-center gap-2 font-bold">
                            <input type="checkbox" name="items[]" value="{{ $value }}" @checked($checked) @disabled($disabled)
                                   class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            الخمسة {{ $khamsa['khamsa'] }}
                        </span>
                        <span class="text-[11px] mt-1">صفحات {{ $khamsa['from_page'] }}–{{ $khamsa['to_page'] }} ({{ $khamsa['pages'] }} صفحات)</span>
                        @if($khamsa['already_assigned'])
                            <span class="text-[11px] mt-1 text-amber-700 font-bold">قيد المراجعة</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
