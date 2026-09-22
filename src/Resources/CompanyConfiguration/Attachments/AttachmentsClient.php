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
 * Attachments hold one or more files. Each record, such as a vendor or a purchasing
 * document, can reference only one attachment through its `attachment` field.
 */
final readonly class AttachmentsClient
{
    /** @var ResourceGateway<Attachment> */
    private ResourceGateway $gateway;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->gateway = new ResourceGateway(
            $transport,
            $queries,
            'objects/company-config/attachment',
            'company-config/attachment',
            Attachment::fromArray(...),
        );
    }

    public function get(ObjectKey $key): Attachment
    {
        return $this->gateway->get($key);
    }

    /**
     * Query results contain attachment headers only; read an attachment to load its files.
     *
     * @return Page<Attachment>
     */
    public function query(ResourceQuery $query = new ResourceQuery): Page
    {
        return $this->gateway->query($query->select([
            'key', 'id', 'name', 'description', 'folder.key', 'folder.id', 'entity.key',
            'entity.id', 'entity.name', 'href',
        ]));
    }

    public function create(CreateAttachment $attachment): MutationResult
    {
        return $this->gateway->create($attachment->toArray());
    }

    public function update(ObjectKey $key, UpdateAttachment $attachment): MutationResult
    {
        return $this->gateway->update($key, $attachment->toArray());
    }

    public function delete(ObjectKey $key): MutationResult
    {
        return $this->gateway->delete($key);
    }
}
