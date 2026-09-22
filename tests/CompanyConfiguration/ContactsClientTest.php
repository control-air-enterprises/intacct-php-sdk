<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\CompanyConfiguration;

use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Exceptions\InvalidArgument;
use ControlAir\Intacct\Resources\CompanyConfiguration\Contacts\Contact;
use ControlAir\Intacct\Resources\CompanyConfiguration\Contacts\ContactsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Contacts\CreateContact;
use ControlAir\Intacct\Resources\CompanyConfiguration\Contacts\UpdateContact;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\MailingAddress;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use ControlAir\Intacct\ValueObjects\SensitiveString;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ContactsClient::class)]
#[CoversClass(Contact::class)]
#[CoversClass(CreateContact::class)]
#[CoversClass(UpdateContact::class)]
#[CoversClass(MailingAddress::class)]
final class ContactsClientTest extends ApiTestCase
{
    /** The spec's GET /objects/company-config/contact/{key} example. */
    private const GET_FIXTURE = <<<'JSON'
        {"ia::result":{"key":"1257","id":"AMoore","companyName":"Sage","prefix":"Mr","firstName":"Andy","lastName":"Moore","middleName":"Robert","printAs":"Andy Moore","tax":{"isTaxable":true,"group":{"id":"New York","key":"6","href":"/objects/company-config/contact-tax-group/6"},"taxId":"123-12-1234"},"phone1":"9134598676","phone2":null,"mobile":"9133132299","pager":null,"fax":"9134598677","email1":"andy.moore@mycompany.com","email2":null,"URL1":"http://andy.exampledomain.com","URL2":null,"showInContactList":true,"mailingAddress":{"addressLine1":"744 Edgewater Blvd","addressLine2":null,"addressLine3":null,"city":"Kansas City","country":"United States","isoCountryCode":"us","postCode":"66104","state":"KS"},"status":"active","entityUseCode":"A","priceSchedule":{"id":null,"key":null},"discount":null,"priceList":{"id":null,"key":null},"entity":{"key":"54","id":"Western Region","name":"Western Region","href":"/objects/company-config/entity/54"},"href":"/objects/company-config/contact/1257"},"ia::meta":{"totalCount":1,"totalSuccess":1,"totalError":0}}
        JSON;

    public function test_it_maps_a_contact_and_redacts_the_tax_id(): void
    {
        [$contacts, $http] = $this->contacts(new Response(200, [], self::GET_FIXTURE));

        $contact = $contacts->get(new ObjectKey('1257'));

        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/contact/1257', $this->url($http->requests[0]));
        self::assertSame('1257', $contact->key->value);
        self::assertSame('AMoore', $contact->id->value);
        self::assertSame('Andy Moore', $contact->printAs);
        self::assertSame('Mr', $contact->prefix);
        self::assertSame('Andy', $contact->firstName);
        self::assertSame('Robert', $contact->middleName);
        self::assertSame('Moore', $contact->lastName);
        self::assertSame('Sage', $contact->companyName);
        self::assertSame('andy.moore@mycompany.com', $contact->email1);
        self::assertNull($contact->email2);
        self::assertSame('9134598676', $contact->phone1);
        self::assertNull($contact->phone2);
        self::assertSame('9133132299', $contact->mobile);
        self::assertSame('9134598677', $contact->fax);
        self::assertSame('http://andy.exampledomain.com', $contact->url1);
        self::assertNull($contact->url2);
        self::assertSame(RecordStatus::Active, $contact->status);
        self::assertTrue($contact->showInContactList);
        self::assertSame('744 Edgewater Blvd', $contact->mailingAddress?->addressLine1);
        self::assertNull($contact->mailingAddress->addressLine2);
        self::assertSame('Kansas City', $contact->mailingAddress->city);
        self::assertSame('KS', $contact->mailingAddress->state);
        self::assertSame('66104', $contact->mailingAddress->postCode);
        self::assertSame('United States', $contact->mailingAddress->country);
        self::assertSame('us', $contact->mailingAddress->isoCountryCode);
        self::assertTrue($contact->taxable);
        self::assertSame('6', $contact->taxGroup?->key?->value);
        self::assertSame('New York', $contact->taxGroup->id?->value);
        self::assertSame('123-12-1234', $contact->taxId?->reveal());
        self::assertSame(['value' => '[redacted]'], $contact->taxId->__debugInfo());
        self::assertSame('Western Region', $contact->entity?->name);
        self::assertSame('/objects/company-config/contact/1257', $contact->href);
    }

    public function test_query_rows_with_dotted_keys_populate_nested_objects(): void
    {
        [$contacts, $http] = $this->contacts($this->json([
            'ia::result' => [[
                'key' => '1257',
                'id' => 'AMoore',
                'printAs' => 'Andy Moore',
                'email1' => 'andy.moore@mycompany.com',
                'status' => 'inactive',
                'mailingAddress.addressLine1' => '744 Edgewater Blvd',
                'mailingAddress.addressLine2' => null,
                'mailingAddress.city' => 'Kansas City',
                'mailingAddress.isoCountryCode' => 'us',
                'tax.isTaxable' => false,
                'tax.group.key' => '6',
                'tax.group.id' => 'New York',
                'entity.key' => null,
                'entity.id' => null,
            ], [
                'key' => '1258',
                'id' => 'CYoung',
                'printAs' => 'Caroline Young',
                'mailingAddress.addressLine1' => null,
                'mailingAddress.city' => null,
                'tax.group.key' => null,
                'tax.group.id' => null,
            ]],
            'ia::meta' => ['totalCount' => 2, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $page = $contacts->query();

        $first = $page->items[0];
        self::assertSame(RecordStatus::Inactive, $first->status);
        self::assertSame('Kansas City', $first->mailingAddress?->city);
        self::assertSame('us', $first->mailingAddress->isoCountryCode);
        self::assertNull($first->mailingAddress->addressLine2);
        self::assertFalse($first->taxable);
        self::assertSame('New York', $first->taxGroup?->id?->value);
        self::assertNull($first->taxId);
        self::assertNull($first->entity);
        self::assertNull($page->items[1]->mailingAddress);
        self::assertNull($page->items[1]->taxGroup);

        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        self::assertSame('company-config/contact', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertContains('URL1', $body['fields']);
        self::assertContains('mailingAddress.isoCountryCode', $body['fields']);
        self::assertContains('tax.group.id', $body['fields']);
        self::assertNotContains('tax.taxId', $body['fields']);
    }

    public function test_it_sends_the_spec_create_payload(): void
    {
        [$contacts, $http] = $this->contacts($this->json([
            'ia::result' => ['key' => '312', 'id' => 'AMoore', 'href' => '/objects/company-config/contact/312'],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $result = $contacts->create(new CreateContact(
            id: new ObjectId('AMoore'),
            printAs: 'Andy Moore',
            prefix: 'Mr',
            firstName: 'Andy',
            middleName: 'Robert',
            lastName: 'Moore',
            companyName: 'Sage',
            email1: 'andy.moore@mycompany.com',
            phone1: '9134598676',
            mobile: '9133132299',
            fax: '9134598677',
            url1: 'http://andy.exampledomain.com',
            status: RecordStatus::Active,
            mailingAddress: new MailingAddress(
                addressLine1: '744 Edgewater Blvd',
                city: 'Kansas City',
                state: 'KS',
                postCode: '66104',
                isoCountryCode: 'us',
            ),
            taxable: true,
            taxGroup: ObjectReference::byId('New York'),
            taxId: new SensitiveString('123-12-1234'),
        ));

        self::assertSame('312', $result->reference->key?->value);
        self::assertSame('POST', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/contact', $this->url($http->requests[0]));
        // The spec example, less entityUseCode, which the SDK does not map.
        self::assertEquals([
            'id' => 'AMoore',
            'prefix' => 'Mr',
            'firstName' => 'Andy',
            'lastName' => 'Moore',
            'middleName' => 'Robert',
            'printAs' => 'Andy Moore',
            'companyName' => 'Sage',
            'phone1' => '9134598676',
            'mobile' => '9133132299',
            'fax' => '9134598677',
            'email1' => 'andy.moore@mycompany.com',
            'URL1' => 'http://andy.exampledomain.com',
            'mailingAddress' => [
                'addressLine1' => '744 Edgewater Blvd',
                'city' => 'Kansas City',
                'state' => 'KS',
                'postCode' => '66104',
                'isoCountryCode' => 'us',
            ],
            'tax' => ['isTaxable' => true, 'taxId' => '123-12-1234', 'group' => ['id' => 'New York']],
            'status' => 'active',
        ], $this->jsonBody($http->requests[0]));
    }

    public function test_a_minimal_create_sends_only_the_required_fields(): void
    {
        [$contacts, $http] = $this->contacts($this->mutation('1', 'CYoung'));

        $contacts->create(new CreateContact(new ObjectId('CYoung'), 'Caroline Young', email1: 'info@bakinggoods.com'));

        self::assertSame(
            ['id' => 'CYoung', 'printAs' => 'Caroline Young', 'email1' => 'info@bakinggoods.com'],
            $this->jsonBody($http->requests[0]),
        );
    }

    public function test_it_sends_update_and_delete_payloads(): void
    {
        [$contacts, $http] = $this->contacts($this->mutation('1257'), $this->mutation('1257'), $this->mutation('1257'));

        $contacts->update(new ObjectKey('1257'), UpdateContact::taxable(false));
        $contacts->update(
            new ObjectKey('1257'),
            UpdateContact::email1('andy@example.com')
                ->withEmail2(null)
                ->withUrl1('https://andy.example.com')
                ->withTaxGroup(ObjectReference::byId('Kansas'))
                ->withTaxable(true)
                ->withMailingAddress(new MailingAddress(addressLine2: 'Suite 200'))
                ->withStatus(RecordStatus::Inactive),
        );
        $contacts->delete(new ObjectKey('1257'));

        self::assertSame('PATCH', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/contact/1257', $this->url($http->requests[0]));
        self::assertSame(['tax' => ['isTaxable' => false]], $this->jsonBody($http->requests[0]));
        self::assertSame([
            'email1' => 'andy@example.com',
            'email2' => null,
            'URL1' => 'https://andy.example.com',
            'tax' => ['group' => ['id' => 'Kansas'], 'isTaxable' => true],
            'mailingAddress' => ['addressLine2' => 'Suite 200'],
            'status' => 'inactive',
        ], $this->jsonBody($http->requests[1]));
        self::assertSame('DELETE', $http->requests[2]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/company-config/contact/1257', $this->url($http->requests[2]));
    }

    public function test_it_rejects_a_blank_print_as_name(): void
    {
        $this->expectException(InvalidArgument::class);

        new CreateContact(new ObjectId('AMoore'), ' ');
    }

    public function test_it_rejects_an_empty_mailing_address(): void
    {
        $this->expectException(InvalidArgument::class);

        new MailingAddress;
    }

    /** @return array{ContactsClient, QueueHttpClient} */
    private function contacts(Response ...$responses): array
    {
        [$transport, $http] = $this->transport(...$responses);

        return [new ContactsClient($transport, new QueryClient($transport)), $http];
    }
}
