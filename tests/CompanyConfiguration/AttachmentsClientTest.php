<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CompanyConfiguration;

use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachedFile;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\Attachment;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentFile;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentFolder;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentFoldersClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\CreateAttachment;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\CreateAttachmentFolder;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\UpdateAttachment;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\UpdateAttachmentFolder;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AttachmentsClient::class)]
#[CoversClass(Attachment::class)]
#[CoversClass(AttachedFile::class)]
#[CoversClass(AttachmentFile::class)]
#[CoversClass(CreateAttachment::class)]
#[CoversClass(UpdateAttachment::class)]
#[CoversClass(AttachmentFoldersClient::class)]
#[CoversClass(AttachmentFolder::class)]
#[CoversClass(CreateAttachmentFolder::class)]
#[CoversClass(UpdateAttachmentFolder::class)]
final class AttachmentsClientTest extends ApiTestCase
{
    private const TEXT = 'hello world! this is base64 encoded data';

    private const BASE64 = 'aGVsbG8gd29ybGQhIHRoaXMgaXMgYmFzZTY0IGVuY29kZWQgZGF0YQ==';

    /** The spec's GET /objects/company-config/attachment/{key} example. */
    private const ATTACHMENT_FIXTURE = <<<'JSON'
        {"ia::result":{"key":"17","id":"INV-V1","name":"Vendor 1 Invoices","folder":{"id":"Invoices","key":"1","href":"/objects/company-config/folder/1"},"description":"Invoices for Vendor 1","audit":{"createdBy":"matthew.mikawber","modifiedBy":"matthew.mikawber","modifiedDate":"2023-05-25","createdDate":"2023-05-25"},"files":[{"id":"29","key":"29","attachment":{"key":"17"},"data":"aGVsbG8gd29ybGQhIHRoaXMgaXMgYmFzZTY0IGVuY29kZWQgZGF0YQ==","name":"short file.txt","size":40}],"href":"/objects/company-config/attachment/17"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    /** The spec's GET /objects/company-config/folder/{key} example. */
    private const FOLDER_FIXTURE = <<<'JSON'
        {"ia::result":{"key":"28","id":"2024 Bills","parent":{"id":"Bills","key":"2","href":"/objects/company-config/folder/2"},"description":"Annual bills folder","audit":{"createdDate":"2023-04-01","createdBy":"Admin","modifiedBy":"Admin","modifiedDate":"2023-04-01"},"status":"active","hasSubfolders":false,"hasAttachments":true,"href":"/objects/company-config/folder/28"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    public function test_it_maps_an_attachment_and_decodes_its_files(): void
    {
        [$attachments, $http] = $this->attachments(new Response(200, [], self::ATTACHMENT_FIXTURE));

        $attachment = $attachments->get(new ObjectKey('17'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/attachment/17', $this->url($http->requests[0]));
        self::assertSame('17', $attachment->key->value);
        self::assertSame('INV-V1', $attachment->id->value);
        self::assertSame('Vendor 1 Invoices', $attachment->name);
        self::assertSame('Invoices for Vendor 1', $attachment->description);
        self::assertSame('1', $attachment->folder?->key?->value);
        self::assertSame('Invoices', $attachment->folder->id?->value);
        self::assertNull($attachment->entity);
        self::assertSame('/objects/company-config/attachment/17', $attachment->href);
        self::assertCount(1, $attachment->files);

        $file = $attachment->files[0];
        self::assertSame('29', $file->key->value);
        self::assertSame('short file.txt', $file->name);
        self::assertSame(40, $file->size);
        self::assertSame('17', $file->attachment?->key?->value);
        self::assertTrue($file->hasData());
        self::assertSame(self::BASE64, $file->base64Data());
        self::assertSame(self::TEXT, $file->contents());
    }

    public function test_attached_file_debug_output_omits_the_data(): void
    {
        [$attachments] = $this->attachments(new Response(200, [], self::ATTACHMENT_FIXTURE));

        $file = $attachments->get(new ObjectKey('17'))->files[0];
        $debug = $file->__debugInfo();

        self::assertSame('[redacted]', $debug['data']);
        self::assertSame('short file.txt', $debug['name']);
        self::assertStringNotContainsString(self::BASE64, print_r($file, true));
    }

    public function test_attached_file_without_data_or_with_invalid_data(): void
    {
        $withoutData = AttachedFile::fromArray(['key' => '29', 'name' => 'a.txt']);

        self::assertFalse($withoutData->hasData());
        self::assertNull($withoutData->contents());
        self::assertNull($withoutData->__debugInfo()['data']);

        $this->expectException(MappingException::class);

        AttachedFile::fromArray(['key' => '29', 'data' => 'not base64!'])->contents();
    }

    public function test_query_rows_with_dotted_keys_populate_the_folder(): void
    {
        [$attachments, $http] = $this->attachments($this->json([
            'ia::result' => [[
                'key' => '17',
                'id' => 'INV-V1',
                'name' => 'Vendor 1 Invoices',
                'description' => null,
                'folder.key' => '1',
                'folder.id' => 'Invoices',
                'entity.key' => null,
                'entity.id' => null,
                'href' => '/objects/company-config/attachment/17',
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $attachments->query();

        self::assertSame('Invoices', $page->items[0]->folder?->id?->value);
        self::assertNull($page->items[0]->entity);
        self::assertSame([], $page->items[0]->files);
        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('company-config/attachment', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertContains('folder.id', $body['fields']);
        self::assertNotContains('files', $body['fields']);
    }

    public function test_it_sends_the_spec_create_payloads_with_base64_file_data(): void
    {
        [$attachments, $http] = $this->attachments(
            $this->json([
                'ia::result' => ['key' => '17', 'id' => '2022 Tax', 'href' => '/objects/company-config/attachment/17'],
                'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
            ]),
            $this->mutation('18', '2024 Tax'),
        );

        $result = $attachments->create(new CreateAttachment(
            name: 'Short text file',
            folder: ObjectReference::byKey('1'),
            id: new ObjectId('attach-02'),
            files: [AttachmentFile::fromContents('short file.txt', self::TEXT)],
        ));
        $attachments->create(new CreateAttachment('2024 Tax Forms', ObjectReference::byKey('27'), new ObjectId('2024 Tax')));

        self::assertSame('17', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/attachment', $this->url($http->requests[0]));
        self::assertEquals([
            'id' => 'attach-02',
            'name' => 'Short text file',
            'folder' => ['key' => '1'],
            'files' => [['name' => 'short file.txt', 'data' => self::BASE64]],
        ], $this->jsonBody($http->requests[0]));
        self::assertEquals(
            ['id' => '2024 Tax', 'name' => '2024 Tax Forms', 'folder' => ['key' => '27']],
            $this->jsonBody($http->requests[1]),
        );
    }

    public function test_it_sends_update_and_delete_payloads(): void
    {
        [$attachments, $http] = $this->attachments(
            $this->mutation('17'),
            $this->mutation('17'),
            $this->mutation('17'),
            $this->mutation('17'),
        );

        $attachments->update(new ObjectKey('17'), UpdateAttachment::addFile(
            AttachmentFile::fromContents('short file 2.txt', self::TEXT),
        ));
        $attachments->update(new ObjectKey('17'), UpdateAttachment::removeFile(new ObjectKey('29')));
        $attachments->update(
            new ObjectKey('17'),
            UpdateAttachment::name('2024 Tax Forms')
                ->withDescription(null)
                ->withFolder(ObjectReference::byId('Archive'))
                ->withRemovedFile(new ObjectKey('29'))
                ->withAddedFile(AttachmentFile::fromContents('b.txt', 'b')),
        );
        $attachments->delete(new ObjectKey('17'));

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/attachment/17', $this->url($http->requests[0]));
        self::assertSame(
            ['files' => [['name' => 'short file 2.txt', 'data' => self::BASE64]]],
            $this->jsonBody($http->requests[0]),
        );
        self::assertSame(
            ['files' => [['key' => '29', 'ia::operation' => 'delete']]],
            $this->jsonBody($http->requests[1]),
        );
        self::assertSame([
            'name' => '2024 Tax Forms',
            'description' => null,
            'folder' => ['id' => 'Archive'],
            'files' => [
                ['key' => '29', 'ia::operation' => 'delete'],
                ['name' => 'b.txt', 'data' => 'Yg=='],
            ],
        ], $this->jsonBody($http->requests[2]));
        self::assertSame('DELETE', $http->requests[3]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/attachment/17', $this->url($http->requests[3]));
    }

    public function test_attachment_file_reads_an_explicit_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'intacct-attachment-');
        self::assertIsString($path);

        try {
            file_put_contents($path, self::TEXT);

            $file = AttachmentFile::fromPath($path);
            $renamed = AttachmentFile::fromPath($path, 'short file.txt');
        } finally {
            unlink($path);
        }

        self::assertSame(basename($path), $file->name);
        self::assertSame(self::BASE64, $file->base64Data());
        self::assertSame(40, $file->size);
        self::assertSame(['name' => 'short file.txt', 'data' => self::BASE64], $renamed->toWriteArray());
    }

    public function test_attachment_file_rejects_a_missing_path(): void
    {
        $this->expectException(InvalidArgument::class);

        AttachmentFile::fromPath(sys_get_temp_dir().'/intacct-sdk-missing-'.bin2hex(random_bytes(8)).'.txt');
    }

    public function test_attachment_file_rejects_a_blank_name(): void
    {
        $this->expectException(InvalidArgument::class);

        AttachmentFile::fromContents(' ', self::TEXT);
    }

    public function test_attachment_file_debug_output_omits_the_data(): void
    {
        $file = AttachmentFile::fromContents('short file.txt', self::TEXT);

        self::assertSame(['name' => 'short file.txt', 'size' => 40, 'data' => '[redacted]'], $file->__debugInfo());
        self::assertStringNotContainsString(self::BASE64, print_r($file, true));
        self::assertStringNotContainsString(self::TEXT, print_r($file, true));

        ob_start();
        var_dump($file);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::BASE64, $dump);
        self::assertStringContainsString('[redacted]', $dump);
    }

    public function test_it_maps_a_folder(): void
    {
        [$folders, $http] = $this->folders(new Response(200, [], self::FOLDER_FIXTURE));

        $folder = $folders->get(new ObjectKey('28'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/folder/28', $this->url($http->requests[0]));
        self::assertSame('28', $folder->key->value);
        self::assertSame('2024 Bills', $folder->id->value);
        self::assertSame('Annual bills folder', $folder->description);
        self::assertSame(RecordStatus::Active, $folder->status);
        self::assertSame('Bills', $folder->parent?->id?->value);
        self::assertSame('2', $folder->parent->key?->value);
        self::assertFalse($folder->hasSubfolders);
        self::assertTrue($folder->hasAttachments);
        self::assertSame('/objects/company-config/folder/28', $folder->href);
    }

    public function test_folder_query_rows_with_dotted_keys_populate_the_parent(): void
    {
        [$folders, $http] = $this->folders($this->json([
            'ia::result' => [
                ['key' => '28', 'id' => '2024 Bills', 'status' => 'active', 'parent.key' => '2', 'parent.id' => 'Bills'],
                ['key' => '2', 'id' => 'Bills', 'status' => 'inactive', 'parent.key' => null, 'parent.id' => null],
            ],
            'ia::meta' => ['totalCount' => 2, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $folders->query();

        self::assertSame('Bills', $page->items[0]->parent?->id?->value);
        self::assertNull($page->items[1]->parent);
        self::assertSame(RecordStatus::Inactive, $page->items[1]->status);
        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('company-config/folder', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertContains('parent.id', $body['fields']);
    }

    public function test_it_sends_folder_create_update_and_delete_payloads(): void
    {
        [$folders, $http] = $this->folders(
            $this->json([
                'ia::result' => ['key' => '28', 'id' => '2024 Bills', 'href' => '/objects/company-config/folder/28'],
                'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
            ]),
            $this->mutation('28'),
            $this->mutation('28'),
            $this->mutation('28'),
        );

        $result = $folders->create(new CreateAttachmentFolder(
            new ObjectId('2024 Bills'),
            description: 'Annual bills folder',
            status: RecordStatus::Active,
        ));
        $folders->update(new ObjectKey('28'), UpdateAttachmentFolder::description('2024 bills and dunning notices'));
        $folders->update(
            new ObjectKey('28'),
            UpdateAttachmentFolder::parent(ObjectReference::byKey('2'))->withStatus(RecordStatus::Inactive),
        );
        $folders->delete(new ObjectKey('28'));

        self::assertSame('28', $result->reference->key?->value);
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/folder', $this->url($http->requests[0]));
        self::assertSame(
            ['id' => '2024 Bills', 'description' => 'Annual bills folder', 'status' => 'active'],
            $this->jsonBody($http->requests[0]),
        );
        self::assertSame('PATCH', $http->requests[1]->getMethod());
        self::assertSame(['description' => '2024 bills and dunning notices'], $this->jsonBody($http->requests[1]));
        self::assertSame(['parent' => ['key' => '2'], 'status' => 'inactive'], $this->jsonBody($http->requests[2]));
        self::assertSame('DELETE', $http->requests[3]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/folder/28', $this->url($http->requests[3]));
    }

    /** @return array{AttachmentsClient, QueueHttpClient} */
    private function attachments(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new AttachmentsClient($transport, new QueryClient($transport)), $http];
    }

    /** @return array{AttachmentFoldersClient, QueueHttpClient} */
    private function folders(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new AttachmentFoldersClient($transport, new QueryClient($transport)), $http];
    }
}
