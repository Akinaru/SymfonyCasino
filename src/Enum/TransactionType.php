<?php

namespace App\Enum;

enum TransactionType: string
{
    case MISE = 'mise';
    case GAIN = 'gain';
    case AJUSTEMENT = 'ajustement';
}
