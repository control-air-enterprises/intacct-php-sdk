<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Purchasing\Documents;

enum PurchasingDocumentState: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case PartiallyApproved = 'partiallyApproved';
    case Declined = 'declined';
    case Draft = 'draft';
    case Pending = 'pending';
    case Closed = 'closed';
    case InProgress = 'inProgress';
    case Converted = 'converted';
    case PartiallyConverted = 'partiallyConverted';
    case Exception = 'exception';
    case Analyzing = 'analyzing';
}
