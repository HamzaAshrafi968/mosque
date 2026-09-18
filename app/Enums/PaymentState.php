<?php

namespace App\Enums;

/**
 * حالة السداد مشتقة دائماً من مقارنة الإجمالي بالمبلغ المدفوع — لا تُخزَّن
 * كحالة مستقلة حتى لا تتعارض مع الدفعات الجزئية أو العكس.
 */
enum PaymentState: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مدفوع',
            self::Partial => 'مدفوع جزئياً',
            self::Paid => 'مدفوع',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-gray-100 text-gray-600',
            self::Partial => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-emerald-100 text-emerald-800',
        };
    }
}
