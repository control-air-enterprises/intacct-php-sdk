<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Attachments;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Core\Query\ResourceQuery;
use ControlAir\Intacct\Core\Resource\ResourceGateway;
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

    public function create(CreateAttachmentFolder $folder): MutationResult
    {
        return $this->gateway->create($folder->toArray());
    }

    public function update(ObjectKey $key, UpdateAttachmentFolder $folder): MutationResult
    {
        return $this->gateway->update($key, $folder->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
