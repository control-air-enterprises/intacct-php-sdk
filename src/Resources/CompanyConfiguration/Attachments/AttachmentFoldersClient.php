<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Http\IdempotencyKey;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
use ControlAir\Intacct\Core\Response\BatchResult;
use ControlAir\Intacct\Core\Response\MutationResult;
use ControlAir\Intacct\Core\Response\Page;
use ControlAir\Intacct\ValueObjects\ObjectKey;

/**
 * Folders for attachments (`company-config/folder`). A folder must exist before an
 * attachment can be created in it.
 */
final readonly class AttachmentFoldersClient
{
    /** @var ResourceGateway<AttachmentFolder> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/folder',
            'company-config/folder',
            AttachmentFolder::fromArray(...),
        );
    }

    public function get(ObjectKey $key): AttachmentFolder
    {
        return $this->gateway->get($key);
    }

    /** @return Page<AttachmentFolder> */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'description', 'status', 'parent.key', 'parent.id', 'entity.key',
            'entity.id', 'entity.name', 'hasSubfolders', 'hasAttachments', 'href',
        ]));
    }

    public function create(CreateAttachmentFolder $folder, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->create($folder->toArray(), $idempotencyKey);
    }

    public function update(ObjectKey $key, UpdateAttachmentFolder $folder, ?IdempotencyKey $idempotencyKey = null): MutationResult
    {
        return $this->gateway->update($key, $folder->toArray(), $idempotencyKey);
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }

    /**
     * Creates up to 500 records in one request. With $atomic, Sage rolls back the whole batch
     * when any record fails; otherwise each record succeeds or fails on its own.
     *
     * @param  list<CreateAttachmentFolder>  $records
     */
    public function createMany(array $records, bool $atomic = false, ?IdempotencyKey $idempotencyKey = null): BatchResult
    {
        return $this->gateway->createMany(
            array_map(static fn (CreateAttachmentFolder $record): array => $record->toArray(), $records),
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
