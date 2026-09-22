<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Contacts;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final readonly class ContactsClient
{
    /** @var ResourceGateway<Contact> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/contact',
            'company-config/contact',
            Contact::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Contact
    {
        return $this->gateway->get($key);
    }

    /**
     * The contact tax ID is excluded from query results; read a single contact to retrieve it.
     *
     * @return Page<Contact>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'printAs', 'prefix', 'firstName', 'middleName', 'lastName',
            'companyName', 'email1', 'email2', 'phone1', 'phone2', 'mobile', 'fax', 'URL1',
            'URL2', 'status', 'showInContactList', 'mailingAddress.addressLine1',
            'mailingAddress.addressLine2', 'mailingAddress.addressLine3', 'mailingAddress.city',
            'mailingAddress.state', 'mailingAddress.postCode', 'mailingAddress.country',
            'mailingAddress.isoCountryCode', 'tax.isTaxable', 'tax.group.key', 'tax.group.id',
            'entity.key', 'entity.id', 'entity.name', 'href',
        ]));
    }

    public function create(CreateContact $contact): MutationResult
    {
        return $this->gateway->create($contact->toArray());
    }

    public function update(ObjectKey $key, UpdateContact $contact): MutationResult
    {
        return $this->gateway->update($key, $contact->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
