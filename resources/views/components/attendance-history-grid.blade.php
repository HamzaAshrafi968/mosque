@props([
    'sessions',  // collection of AttendanceSession
    'rows',      // [['student' => Student, 'cells' => [sessionId => status|null], 'stats' => [...]], ...]
    'empty' => 'لا توجد جلسات حضور في هذه الفترة',
])

<div class="bg-white rounded-xl shadow overflow-hidden">
    @if($sessions->isEmpty())
        <div class="p-6 text-center text-gray-500">{{ $empty }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-3 py-3 text-right whitespace-nowrap">الطالب</th>
                        @foreach($sessions as $session)
                            <th class="px-2 py-3 text-center whitespace-nowrap">
                                <div dir="ltr">{{ $session->date->format('Y-m-d') }}</div>
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center whitespace-nowrap">نسبة الحضور</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-3 py-2 border-t font-bold whitespace-nowrap">{{ $row['student']->name }}</td>
                            @foreach($sessions as $session)
                                <td class="px-2 py-2 border-t text-center">
                                    <x-attendance-status-badge :status="$row['cells'][$session->id] ?? null" />
                                </td>
                            @endforeach
                            <td class="px-3 py-2 border-t text-center font-bold whitespace-nowrap">
                                @if($row['stats']['percentage'] !== null)
                                    <span class="text-emerald-700">{{ $row['stats']['percentage'] }}%</span>
                                    <div class="text-[11px] text-gray-400 font-normal" dir="ltr">
                                        {{ $row['stats']['attended'] }}/{{ $row['stats']['total'] }}
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $sessions->count() + 2 }}" class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب مسجلون في هذه الشعبة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t bg-gray-50 text-xs text-gray-500 space-y-1">
            <div>قاعدة احتساب النسبة: حاضر ومتأخر = حضر، غائب = لا يحتسب حضوراً، معذور = مستبعد من المقام والبسط.</div>
            <div>
                @foreach(\App\Enums\AttendanceStatus::cases() as $status)
                    <span class="ml-3"><x-attendance-status-badge :status="$status" /> <span class="mr-1">{{ $status->label() }}</span></span>
                @endforeach
            </div>
        </div>
    @endif
</div>
