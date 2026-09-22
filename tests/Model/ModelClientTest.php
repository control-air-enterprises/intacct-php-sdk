<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\Model;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Model\AllowedOperations;
use ControlAir\Intacct\Core\Model\AllowedOperationsResult;
use ControlAir\Intacct\Core\Model\FieldDefinition;
use ControlAir\Intacct\Core\Model\GroupDefinition;
use ControlAir\Intacct\Core\Model\ModelClient;
use ControlAir\Intacct\Core\Model\ModelMapper;
use ControlAir\Intacct\Core\Model\ObjectModel;
use ControlAir\Intacct\Core\Model\RelationshipDefinition;
use ControlAir\Intacct\Core\Model\ResourceCatalog;
use ControlAir\Intacct\Core\Model\ResourceSummary;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelClient::class)]
#[CoversClass(ObjectModel::class)]
#[CoversClass(FieldDefinition::class)]
#[CoversClass(GroupDefinition::class)]
#[CoversClass(RelationshipDefinition::class)]
#[CoversClass(ResourceCatalog::class)]
#[CoversClass(ResourceSummary::class)]
#[CoversClass(AllowedOperations::class)]
#[CoversClass(AllowedOperationsResult::class)]
#[CoversClass(ModelMapper::class)]
final class ModelClientTest extends TestCase
{
    private const BASE = 'https://api.intacct.com/ia/api/v1/';

    /** Verbatim "Model for department" example from the spec. */
    private const DEPARTMENT_MODEL = <<<'JSON'
        {"ia::result":{"fields":{"id":{"mutable":false,"nullable":false,"type":"string","readOnly":false,"writeOnly":false,"required":false},"key":{"readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false},"name":{"nullable":false,"type":"string","readOnly":false,"writeOnly":false,"required":false,"mutable":true},"reportTitle":{"nullable":true,"type":"string","readOnly":false,"writeOnly":false,"required":false,"mutable":true},"status":{"enum":["active","activeNonPosting","inactive"],"type":"string","readOnly":false,"writeOnly":false,"required":false,"nullable":false,"mutable":true}},"groups":{"audit":{"fields":{"createdBy":{"readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false},"createdDateTime":{"format":"date-time","readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false},"modifiedBy":{"readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false},"modifiedDateTime":{"format":"date-time","readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false}}}},"httpMethods":"OPTIONS,GET,POST,DELETE,PATCH","refs":{"parent":{"apiObject":"company-config/department","fields":{"id":{"type":"string","readOnly":false,"writeOnly":false,"required":false,"nullable":true,"mutable":true},"key":{"nullable":true,"type":"string","readOnly":false,"writeOnly":false,"required":false,"mutable":true},"name":{"readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false}}},"supervisor":{"apiObject":"company-config/employee","fields":{"id":{"type":"string","readOnly":false,"writeOnly":false,"required":false,"nullable":true,"mutable":true},"key":{"nullable":true,"type":"string","readOnly":false,"writeOnly":false,"required":false,"mutable":true},"name":{"readOnly":true,"type":"string","writeOnly":false,"required":false,"nullable":true,"mutable":false}}}},"lists":[],"idempotenceSupported":true},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    /** Verbatim allowed-operations request example from the spec. */
    private const ALLOWED_OPERATIONS_REQUEST = <<<'JSON'
        {"object":"accounts-payable/vendor","keys":["1","6","65"],"operations":["canView","canEdit","canDelete"],"options":{"includePrivate":true},"additionalData":{"requestType":"vendor-list"}}
        JSON;

    /** Verbatim allowed-operations response example from the spec. */
    private const ALLOWED_OPERATIONS = <<<'JSON'
        {"ia::result":[{"key":"1","operations":["canView","canEdit","canDelete"],"additionalData":{"operationCode":123}},{"key":"6","operations":["canView","canEdit"],"additionalData":{"operationCode":0}}],"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    public function test_it_lists_resources_as_a_typed_catalog(): void
    {
        [$models, $http] = $this->models(new Response(200, [], json_encode([
            'ia::result' => [
                [
                    'apiObject' => 'objects/company-config/department',
                    'type' => 'rootObject',
                    'httpMethods' => 'OPTIONS,GET,DELETE,PATCH,POST',
                    'href' => '/services/core/model?version=v1&name=objects/company-config/department',
                ],
                [
                    'apiObject' => 'objects/company-config/department-group-member',
                    'type' => 'ownedObject',
                    'httpMethods' => 'OPTIONS,GET',
                    'href' => '/services/core/model?version=v1&name=objects/company-config/department-group-member',
                ],
                [
                    'apiObject' => 'objects/platform-apps/nsp::travel_UDD',
                    'type' => 'rootObject',
                    'httpMethods' => 'OPTIONS,GET,POST',
                    'href' => '/services/core/model?version=v1&name=objects/platform-apps/nsp::travel_UDD',
                ],
            ],
            'ia::meta' => ['totalCount' => 3],
        ], JSON_THROW_ON_ERROR)));

        $catalog = $models->list();

        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame(self::BASE.'services/core/model', (string) $http->requests[0]->getUri());
        self::assertCount(3, $catalog->resources);
        self::assertSame(3, $catalog->meta->totalCount);
        self::assertFalse($catalog->isEmpty());

        $department = $catalog->find('company-config/department');
        self::assertNotNull($department);
        self::assertSame($department, $catalog->find('objects/company-config/department'));
        self::assertSame('company-config/department', $department->name());
        self::assertSame('rootObject', $department->type);
        self::assertSame(['OPTIONS', 'GET', 'DELETE', 'PATCH', 'POST'], $department->httpMethods);
        self::assertTrue($department->supportsMethod('patch'));
        self::assertFalse($department->isCustom());

        self::assertCount(1, $catalog->ofType('ownedObject'));
        self::assertFalse($catalog->resources[1]->supportsMethod('POST'));
        self::assertSame(['platform-apps/nsp::travel_UDD'], array_map(
            static fn (ResourceSummary $resource): string => $resource->name(),
            $catalog->customObjects(),
        ));
        self::assertNull($catalog->find('accounts-payable/vendor'));
    }

    public function test_list_passes_filters_through_the_query_string(): void
    {
        [$models, $http] = $this->models($this->json(['ia::result' => [], 'ia::meta' => ['totalCount' => 0]]));

        $catalog = $models->list(type: 'workflow', filter: '.*company-config\/cla.*', version: 'v1');

        self::assertTrue($catalog->isEmpty());
        self::assertSame('/ia/api/v1/services/core/model', $http->requests[0]->getUri()->getPath());
        self::assertSame(
            ['type' => 'workflow', 'version' => 'v1', 'filter' => '.*company-config\/cla.*'],
            $this->queryParameters($http),
        );
    }

    public function test_it_describes_the_department_model_from_the_spec(): void
    {
        [$models, $http] = $this->models(new Response(200, [], self::DEPARTMENT_MODEL));

        $model = $models->describe('company-config/department');

        self::assertSame(self::BASE.'services/core/model?name=company-config%2Fdepartment', (string) $http->requests[0]->getUri());
        self::assertNotNull($model);
        self::assertSame(['id', 'key', 'name', 'reportTitle', 'status'], array_keys($model->fields));
        self::assertSame(['OPTIONS', 'GET', 'POST', 'DELETE', 'PATCH'], $model->httpMethods);
        self::assertTrue($model->idempotenceSupported);
        self::assertTrue($model->supportsMethod('DELETE'));
        self::assertFalse($model->isServiceOrWorkflow());
        self::assertSame([], $model->lists);
        self::assertSame([], $model->customFields());

        $key = $model->fields['key'];
        self::assertSame('key', $key->name);
        self::assertSame('string', $key->type);
        self::assertTrue($key->readOnly);
        self::assertTrue($key->nullable);
        self::assertFalse($key->mutable);
        self::assertFalse($key->required);
        self::assertFalse($key->isCustom());

        $id = $model->fields['id'];
        self::assertFalse($id->readOnly);
        self::assertFalse($id->nullable);
        self::assertFalse($id->mutable);

        $status = $model->fields['status'];
        self::assertTrue($status->isEnum());
        self::assertSame(['active', 'activeNonPosting', 'inactive'], $status->enumValues);
        self::assertNull($status->format);

        self::assertSame(['audit'], array_keys($model->groups));
        $audit = $model->groups['audit'];
        self::assertSame('audit', $audit->name);
        self::assertCount(4, $audit->fields);
        self::assertSame('date-time', $audit->field('createdDateTime')?->format);
        self::assertTrue($audit->fields['modifiedBy']->readOnly);
        self::assertSame($audit->fields['createdBy'], $model->field('audit.createdBy'));

        self::assertSame(['parent', 'supervisor'], array_keys($model->refs));
        $supervisor = $model->refs['supervisor'];
        self::assertSame('company-config/employee', $supervisor->apiObject);
        self::assertSame(['id', 'key', 'name'], array_keys($supervisor->fields));
        self::assertTrue($supervisor->fields['name']->readOnly);
        self::assertFalse($supervisor->isCustom());
        self::assertSame('company-config/department', $model->refs['parent']->apiObject);
        self::assertSame($model->refs['parent']->fields['id'], $model->field('parent.id'));

        self::assertSame($model->fields['name'], $model->field('name'));
        self::assertNull($model->field('missing'));
        self::assertNull($model->field('audit.missing'));
        self::assertNull($model->field('nothing.here'));
    }

    public function test_describe_passes_type_version_and_description_through_the_query_string(): void
    {
        [$models, $http] = $this->models(new Response(200, [], self::DEPARTMENT_MODEL));

        $models->describe('projects/task', type: 'object', version: 'v1', descriptions: true);

        self::assertSame(
            ['name' => 'projects/task', 'type' => 'object', 'version' => 'v1', 'description' => 'true'],
            $this->queryParameters($http),
        );
    }

    public function test_it_detects_custom_fields_and_custom_relationships(): void
    {
        [$models, $http] = $this->models($this->json([
            'ia::result' => [
                'apiObject' => 'accounts-payable/vendor',
                'type' => 'rootObject',
                'fields' => [
                    'id' => ['type' => 'string', 'readOnly' => false, 'nullable' => false],
                    'retainagePercentage' => ['type' => 'number', 'readOnly' => false, 'nullable' => true],
                    'nsp::CUSTOM_CHECKBOX' => ['type' => 'boolean', 'readOnly' => false, 'nullable' => false],
                    'nsp::CUSTOM_EMAIL' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                    'nsp::PICKLIST' => ['type' => 'string', 'enum' => ['one', 'two', null], 'nullable' => true],
                    'nsp::MULTI_PICKLIST' => ['type' => 'array', 'nullable' => true],
                    'nsp::SEQUENCE' => ['type' => 'string', 'readOnly' => true],
                ],
                'groups' => [
                    'audit' => ['fields' => ['createdBy' => ['type' => 'string', 'readOnly' => true]]],
                ],
                'refs' => [
                    'term' => ['apiObject' => 'accounts-payable/term', 'fields' => ['id' => ['type' => 'string']]],
                    'nsp::r10258' => ['apiObject' => 'platform-apps/nsp::project_site', 'fields' => ['key' => ['type' => 'string']]],
                ],
                'lists' => [],
                'httpMethods' => 'OPTIONS,GET,POST,PATCH,DELETE',
            ],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $model = $models->describe('accounts-payable/vendor');

        self::assertSame(self::BASE.'services/core/model?name=accounts-payable%2Fvendor', (string) $http->requests[0]->getUri());
        self::assertNotNull($model);
        self::assertSame('accounts-payable/vendor', $model->apiObject);
        self::assertSame('rootObject', $model->type);

        $custom = $model->customFields();
        self::assertSame(
            ['nsp::CUSTOM_CHECKBOX', 'nsp::CUSTOM_EMAIL', 'nsp::PICKLIST', 'nsp::MULTI_PICKLIST', 'nsp::SEQUENCE'],
            array_map(static fn (FieldDefinition $field): string => $field->name, $custom),
        );
        self::assertTrue($model->fields['nsp::CUSTOM_CHECKBOX']->isCustom());
        self::assertFalse($model->fields['retainagePercentage']->isCustom());
        self::assertSame('email', $model->fields['nsp::CUSTOM_EMAIL']->format);
        self::assertSame(['one', 'two', null], $model->fields['nsp::PICKLIST']->enumValues);
        self::assertTrue($model->fields['nsp::SEQUENCE']->readOnly);
        self::assertFalse($model->fields['nsp::SEQUENCE']->mutable, 'mutable defaults to the inverse of readOnly');
        self::assertTrue($model->fields['nsp::CUSTOM_CHECKBOX']->mutable);

        $relationships = $model->customRelationships();
        self::assertCount(1, $relationships);
        self::assertSame('nsp::r10258', $relationships[0]->name);
        self::assertSame('platform-apps/nsp::project_site', $relationships[0]->apiObject);
        self::assertSame([], $model->groups['audit']->customFields());
    }

    public function test_mapping_tolerates_unknown_keys_and_alternative_shapes(): void
    {
        [$models] = $this->models($this->json([
            'ia::result' => [
                'fields' => [
                    'id' => [
                        'type' => 'string',
                        'readOnly' => false,
                        'description' => 'Unique identifier.',
                        'examples' => ['D-100'],
                        'x-unknown' => ['nested' => true],
                    ],
                    'broken' => 'not-an-object',
                    'priority' => ['type' => 'integer', 'enum' => [1, 2, ['bad']], 'readOnly' => 'yes'],
                ],
                'groups' => [
                    ['audit' => ['fields' => ['createdBy' => ['type' => 'string']]]],
                    ['name' => 'dimensions', 'refs' => ['nsp::vssn' => ['apiObject' => 'platform-apps/nsp::vssn']]],
                    ['fields' => ['orphan' => ['type' => 'string']]],
                    'garbage',
                ],
                'refs' => [
                    ['apiObject' => 'company-config/employee', 'fields' => []],
                ],
                'lists' => [
                    'lines' => ['apiObject' => 'accounts-payable/bill-line', 'somethingNew' => 1],
                ],
                'httpMethods' => ['get', 'post', 7],
                'idempotenceSupported' => 'maybe',
                'futureTopLevelKey' => ['anything' => 'goes'],
            ],
            'ia::meta' => ['totalCount' => 1, 'extra' => 'ignored'],
        ]));

        $model = $models->describe('company-config/department', descriptions: true);

        self::assertNotNull($model);
        self::assertSame(['id', 'priority'], array_keys($model->fields));
        self::assertSame('Unique identifier.', $model->fields['id']->description);
        self::assertSame(['nested' => true], $model->fields['id']->attributes['x-unknown']);
        self::assertSame([1, 2], $model->fields['priority']->enumValues);
        self::assertFalse($model->fields['priority']->readOnly);
        self::assertNull($model->idempotenceSupported);
        self::assertSame(['GET', 'POST'], $model->httpMethods);

        self::assertSame(['audit', 'dimensions'], array_keys($model->groups));
        self::assertSame('string', $model->field('audit.createdBy')?->type);
        self::assertTrue($model->groups['dimensions']->refs['nsp::vssn']->isCustom());

        self::assertSame(['company-config/employee'], array_keys($model->refs));
        self::assertSame('accounts-payable/bill-line', $model->lists['lines']->apiObject);
    }

    public function test_it_maps_service_models_with_request_and_response_sections(): void
    {
        [$models] = $this->models($this->json([
            'ia::result' => [
                'request' => [
                    'fields' => ['documentType' => ['type' => 'string']],
                    'groups' => [],
                    'refs' => [['name' => 'vendor', 'apiObject' => 'accounts-payable/vendor']],
                    'lists' => [],
                    'httpMethods' => 'POST',
                ],
                'response' => [
                    'fields' => ['nextSequence' => ['type' => 'string', 'readOnly' => true]],
                    'groups' => [],
                    'refs' => [],
                    'lists' => [],
                    'httpMethods' => 'POST',
                ],
                'idempotenceSupported' => false,
                'apiObject' => 'services/company-config/document-sequence/get-next-sequence',
                'type' => 'service',
                'httpMethods' => 'OPTIONS,POST',
            ],
            'ia::meta' => ['totalCount' => 1],
        ]));

        $model = $models->describe('company-config/document-sequence/get-next-sequence', type: 'service');

        self::assertNotNull($model);
        self::assertTrue($model->isServiceOrWorkflow());
        self::assertSame('service', $model->type);
        self::assertFalse($model->idempotenceSupported);
        self::assertSame([], $model->fields);
        self::assertNotNull($model->request);
        self::assertNotNull($model->response);
        self::assertSame('string', $model->request->field('documentType')?->type);
        self::assertSame('accounts-payable/vendor', $model->request->refs['vendor']->apiObject);
        self::assertTrue($model->response->fields['nextSequence']->readOnly);
    }

    public function test_describe_returns_null_when_the_resource_is_not_found(): void
    {
        [$models] = $this->models($this->json(['ia::result' => [], 'ia::meta' => ['totalCount' => 0]]));

        self::assertNull($models->describe('company-config/nothing'));
    }

    public function test_describe_rejects_a_result_that_is_not_an_object(): void
    {
        [$models] = $this->models($this->json(['ia::result' => ['v1', 'v2'], 'ia::meta' => ['totalCount' => 2]]));

        $this->expectException(MappingException::class);

        $models->describe('company-config/department', version: 'all');
    }

    public function test_list_rejects_non_object_items(): void
    {
        [$models] = $this->models($this->json(['ia::result' => ['objects/company-config/department']]));

        $this->expectException(MappingException::class);

        $models->list();
    }

    public function test_describe_rejects_a_blank_name(): void
    {
        [$models, $http] = $this->models();

        try {
            $models->describe(' ');
            self::fail('A blank name must be rejected.');
        } catch (InvalidArgument) {
            self::assertSame([], $http->requests);
        }
    }

    public function test_it_lists_allowed_operations_using_the_spec_example(): void
    {
        [$models, $http] = $this->models(new Response(200, [], self::ALLOWED_OPERATIONS));

        $result = $models->allowedOperations(
            'accounts-payable/vendor',
            ['1', new ObjectKey('6'), '65'],
            ['canView', 'canEdit', 'canDelete'],
            includePrivate: true,
            additionalData: ['requestType' => 'vendor-list'],
        );

        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::BASE.'services/core/allowed-operations/list', (string) $request->getUri());
        self::assertSame(
            json_decode(self::ALLOWED_OPERATIONS_REQUEST, true, flags: JSON_THROW_ON_ERROR),
            json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );

        self::assertCount(2, $result->records);
        self::assertSame(['canView', 'canEdit', 'canDelete'], $result->for('1')?->operations);
        self::assertSame(['operationCode' => 123], $result->for(new ObjectKey('1'))?->additionalData);
        self::assertTrue($result->allows('6', 'canEdit'));
        self::assertFalse($result->allows('6', 'canDelete'));
        self::assertFalse($result->allows('65', 'canView'));
        self::assertNull($result->for('65'));
    }

    public function test_allowed_operations_omits_optional_members(): void
    {
        [$models, $http] = $this->models($this->json(['ia::result' => [], 'ia::meta' => ['totalCount' => 0]]));

        $models->allowedOperations('accounts-payable/vendor', ['1'], moduleKey: '3.AP');

        self::assertSame(
            ['object' => 'accounts-payable/vendor', 'keys' => ['1'], 'options' => ['moduleKey' => '3.AP']],
            json_decode((string) $http->requests[0]->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_allowed_operations_validates_the_key_count(): void
    {
        [$models] = $this->models();

        $this->expectException(InvalidArgument::class);

        $models->allowedOperations('accounts-payable/vendor', array_map('strval', range(1, 1001)));
    }

    /** @return array{ModelClient, QueueHttpClient} */
    private function models(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;

        return [
            new ModelClient(new ApiTransport(
                new StaticAccessTokenProvider(new AccessToken('token')),
                $http,
                $factory,
                $factory,
            )),
            $http,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): Response
    {
        return new Response(200, [], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<int|string, mixed> */
    private function queryParameters(QueueHttpClient $http): array
    {
        parse_str($http->requests[0]->getUri()->getQuery(), $parameters);

        return $parameters;
    }
}
