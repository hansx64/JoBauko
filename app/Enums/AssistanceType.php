<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AssistanceType: string
{
    use HasOptions;

    case StarterKit = 'starter_kit';
    case CashGrant = 'cash_grant';
    case Livestock = 'livestock';
    case FarmInputs = 'farm_inputs';
    case Equipment = 'equipment';
    case Loan = 'loan';
    case CashForWork = 'cash_for_work';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StarterKit => 'Starter kit',
            self::CashGrant => 'Cash grant',
            self::Livestock => 'Livestock dispersal',
            self::FarmInputs => 'Seeds and farm inputs',
            self::Equipment => 'Tools and equipment',
            self::Loan => 'Loan / microfinance',
            self::CashForWork => 'Cash-for-work / emergency employment',
            self::Other => 'Other assistance',
        };
    }
}
