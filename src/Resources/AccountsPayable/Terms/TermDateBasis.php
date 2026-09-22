<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\AccountsPayable\Terms;

/** The date a term's due or discount period is counted from. */
enum TermDateBasis: string
{
    case FromBillDate = 'fromBillDate';
    case OfTheMonthOfBillDate = 'ofTheMonthOfBillDate';
    case OfNextMonthFromBillDate = 'ofNextMonthFromBillDate';
    case Of2ndMonthFromBillDate = 'of2ndMonthFromBillDate';
    case Of3rdMonthFromBillDate = 'of3rdMonthFromBillDate';
    case Of4thMonthFromBillDate = 'of4thMonthFromBillDate';
    case Of5thMonthFromBillDate = 'of5thMonthFromBillDate';
    case Of6thMonthFromBillDate = 'of6thMonthFromBillDate';
    case AfterEndOfMonthOfBillDate = 'afterEndOfMonthOfBillDate';
    case FromBillDateExtendingToEom = 'fromBillDateExtendingToEom';
}
