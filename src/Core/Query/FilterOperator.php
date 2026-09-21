<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

enum FilterOperator: string
{
    case Equal = '$eq';
    case NotEqual = '$ne';
    case LessThan = '$lt';
    case LessThanOrEqual = '$lte';
    case GreaterThan = '$gt';
    case GreaterThanOrEqual = '$gte';
    case In = '$in';
    case NotIn = '$notIn';
    case Between = '$between';
    case NotBetween = '$notBetween';
    case Contains = '$contains';
    case NotContains = '$notContains';
    case Has = '$has';
    case StartsWith = '$startsWith';
    case NotStartsWith = '$notStartsWith';
    case EndsWith = '$endsWith';
    case NotEndsWith = '$notEndsWith';
}
