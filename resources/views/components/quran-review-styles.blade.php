@once
@push('styles')
<style>
    .quran-word {
        display: inline-block;
        padding: 5px 10px;
        margin: 2px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1.6rem;
        font-family: 'Amiri', 'Scheherazade New', 'Traditional Arabic', 'UthmanicHafs', serif;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        line-height: 2.5;
        position: relative;
    }
    .quran-word:hover {
        transform: scale(1.08) translateY(-1px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.12);
    }
    .quran-word.status-unreviewed  { background: #f1f5f9; color: #64748b; border: 1px dashed #cbd5e1; }
    .quran-word.status-correct     { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
    .quran-word.status-incorrect   { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border: 1px solid #fca5a5; }
    .quran-word.status-hesitation  { background: linear-gradient(135deg, #fef9c3, #fef08a); color: #854d0e; border: 1px solid #fde047; }
    .quran-word.status-tajweed_error { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
    .quran-word.status-added       { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #9d174d; border: 1px solid #f9a8d4; }
    .quran-word.status-forgotten   { background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #9a3412; border: 1px solid #fdba74; }
    .quran-word.active-word {
        outline: 3px solid #7c3aed;
        outline-offset: 2px;
        z-index: 30;
        transform: scale(1.1);
        box-shadow: 0 4px 18px rgba(124, 58, 237, 0.3);
    }
    .error-popup {
        position: absolute;
        z-index: 50;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
        padding: 14px;
        min-width: 230px;
        animation: scaleIn 0.2s ease-out;
    }
    .error-popup button {
        transition: all 0.15s ease;
    }
    .error-popup button:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .status-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 14px 18px;
        background: white;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 0.8rem;
        color: #4b5563;
    }
    .legend-dot {
        width: 16px;
        height: 16px;
        border-radius: 5px;
        border: 1px solid rgba(0,0,0,0.1);
    }
    .review-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
    }
    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 14px 12px;
        text-align: center;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    }
    .stat-value {
        font-size: 1.6rem;
        font-weight: 800;
    }
    .mastery-bar {
        height: 10px;
        border-radius: 5px;
        background: #e5e7eb;
        overflow: hidden;
        margin-top: 8px;
    }
    .mastery-fill {
        height: 100%;
        border-radius: 5px;
        background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7);
        transition: width 0.4s ease;
    }
    .shortcut-key {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        font-size: 0.7rem;
        font-weight: bold;
        color: #64748b;
        padding: 0 5px;
    }
    .surah-header {
        background: linear-gradient(135deg, #064e3b, #065f46, #047857);
        border-radius: 20px;
        padding: 20px 28px;
        color: white;
        box-shadow: 0 4px 20px rgba(6, 78, 59, 0.2);
    }

    /* داخل إطار المصحف: الكلمات تبقى نصاً عادياً حتى يتم تعليمها */
    .mushaf-review {
        margin-bottom: 1.25rem;
    }
    .mushaf-review .quran-word {
        display: inline;
        padding: 0 2px;
        margin: 0;
        border: 1px solid transparent;
        border-radius: 6px;
        font-size: inherit;
        font-family: inherit;
        line-height: inherit;
    }
    .mushaf-review .quran-word:hover {
        transform: none;
        box-shadow: none;
    }
    .mushaf-review .quran-word.status-correct,
    .mushaf-review .quran-word.status-unreviewed {
        background: transparent;
        border-color: transparent;
        color: inherit;
    }
    .mushaf-review .quran-word.status-correct:hover,
    .mushaf-review .quran-word.status-unreviewed:hover {
        background: rgba(16, 185, 129, 0.14);
    }
    .mushaf-review .quran-word.active-word {
        outline: 2px solid #7c3aed;
        outline-offset: 1px;
        transform: none;
        box-shadow: none;
    }
</style>
@endpush
@endonce
