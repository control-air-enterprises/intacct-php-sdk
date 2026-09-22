<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Contacts;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
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

    public function create(CreateContact $contact, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($contact->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateContact $contact, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $contact->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateContact>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateContact $record): array => $record->toArray(), $records),
            $atomic,
            $idempotencyKey,
        );
    }

    /** @param list<ObjectKey> $keys Up to 500 keys; results are matched to keys by position. */
    public function deleteMany(array $keys, bool $atomic = false): BatchResult
    {
        return $this->gateway->deleteMany($keys, $atomic);
    }
}
