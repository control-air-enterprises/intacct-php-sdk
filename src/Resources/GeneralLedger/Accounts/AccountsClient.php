<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\GeneralLedger\Accounts;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Read-only access to the chart of accounts.
 *
 * The API also accepts POST, PATCH and DELETE for GL accounts, but chart-of-accounts
 * changes affect posting, closing and reporting across the company, so the typed client
 * deliberately exposes no write methods. Make such changes in Sage Intacct itself or
 * through the raw `ApiTransport` where a controlled process requires it.
 */
final readonly class AccountsClient
{
    /** @var ResourceGateway<Account> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/general-ledger/account',
            'general-ledger/account',
            Account::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Account
    {
        return $this->gateway->get($key);
    }

    /** @return Page<Account> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'status', 'accountType', 'normalBalance', 'closingType',
            'closeToGLAccount.key', 'closeToGLAccount.id', 'alternativeGLAccount', 'category',
            'constructionCategory', 'disallowDirectPosting', 'enableGLMatching',
            'isGermanTaxAccount', 'isTaxable', 'reconciliationSequence', 'taxCode', 'mrcCode',
            'requireDimensions.class', 'requireDimensions.contract', 'requireDimensions.customer',
            'requireDimensions.department', 'requireDimensions.employee', 'requireDimensions.item',
            'requireDimensions.location', 'requireDimensions.project', 'requireDimensions.vendor',
            'requireDimensions.warehouse', 'requireDimensions.asset',
            'requireDimensions.affiliateEntity', 'requireDimensions.task',
            'requireDimensions.costType', 'requireDimensions.loanAccount',
            'requireDimensions.isWorkOrderRequired', 'entity.key', 'entity.id', 'entity.name',
            'href',
        ]));
    }
}
