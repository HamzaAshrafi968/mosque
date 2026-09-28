<?php

namespace App\Enums;

/**
 * نوع التبرع/المساهمة: مادي (مال) أو عيني (سلع/خدمات) أو معنوي (وقت/تطوع/مهارات).
 */
enum DonationType: string
{
    case Financial = 'financial';
    case InKind = 'in_kind';
    case Moral = 'moral';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Financial => 'تبرع مادي',
            self::InKind => 'تبرع عيني',
            self::Moral => 'مساهمة معنوية',
            self::Other => 'غير ذلك',
        };
    }

    /** مثال إرشادي يظهر في نموذج التقديم بجانب كل نوع. */
    public function example(): string
    {
        return match ($this) {
            self::Financial => 'مثال: 25 أو 50 أو 100 دولار — أو أي مبلغ بالليرة أو الدولار',
            self::InKind => 'مثال: —  كتب، أثاث، جهاز، مواد…',
            self::Moral => 'مثال: تدريس تطوعي، ورشة أو محاضرة، وقت إشراف، مهارات وخبرات',
            self::Other => 'اكتب نوع مساهمتك بنفسك — أي مساهمة أخرى تريد تقديمها',
        };
    }

    /** هل يتطلب هذا النوع مبلغًا وعملة؟ */
    public function requiresAmount(): bool
    {
        return $this === self::Financial;
    }
}
