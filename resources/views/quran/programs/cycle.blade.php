@php
    $program = $program ?? null;
    $states = $states ?? collect();
    $currentBatch = $currentBatch ?? null;
    $juzGrid = $juzGrid ?? collect();
    $summary = $summary ?? [];
    $tests = $tests ?? collect();
    $actions = $actions ?? [];
    $canTest = $canTest ?? false;
    $canCancel = $canCancel ?? false;
    $minimumPassingPercentage = $minimumPassingPercentage ?? 80;
@endphp

@if (! $program)
    <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
        اختر طالباً وبرنامجاً لعرض دورة البرنامج — «وين موصل» و«شو مسمع».
    </div>
@else
    @include('quran.programs.cycle-batch')
@endif
