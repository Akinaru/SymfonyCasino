<?php

namespace App\Enum;

enum IssueType: string
{
    case GAGNE = 'gagne';
    case PERDU = 'perdu';
    case EGALITE = 'egalite';
}
