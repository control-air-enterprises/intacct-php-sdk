<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Tests\GeneralLedger;

use ControlAir\Intacct\Auth\Tokens\AccessToken;
use ControlAir\Intacct\Auth\Tokens\StaticAccessTokenProvider;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\Account;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\AccountRequiredDimensions;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\AccountsClient;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\AccountType;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\AlternativeGLAccount;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\ClosingType;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\ConstructionCategory;
use ControlAir\Intacct\Resources\GeneralLedger\Accounts\NormalBalance;
use ControlAir\Intacct\Resources\GeneralLedger\GeneralLedger;
use ControlAir\Intacct\Tests\Support\ApiTestCase;
use ControlAir\Intacct\Tests\Support\QueueHttpClient;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\RecordStatus;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionMethod;

#[CoversClass(GeneralLedger::class)]
#[CoversClass(AccountsClient::class)]
#[CoversClass(Account::class)]
#[CoversClass(AccountRequiredDimensions::class)]
final class AccountsClientTest extends ApiTestCase
{
    public function test_it_maps_an_account(): void
    {
        [$accounts, $http] = $this->accounts($this->json([
            'ia::result' => [
                'key' => '356',
                'id' => '1501',
                'name' => 'Vehicle Spare parts - Transmission',
                'accountType' => 'balanceSheet',
                'normalBalance' => 'debit',
                'closingType' => 'nonClosingAccount',
                'closeToGLAccount' => ['id' => null, 'key' => null],
                'status' => 'active',
                'requireDimensions' => [
                    'department' => true,
                    'location' => false,
                    'project' => true,
                    'customer' => false,
                    'vendor' => false,
                    'employee' => false,
                    'item' => false,
                    'class' => false,
                    'contract' => false,
                    'warehouse' => false,
                ],
                'isTaxable' => false,
                'category' => null,
                'taxCode' => null,
                'mrcCode' => null,
                'alternativeGLAccount' => 'none',
                'automaticAccount' => false,
                'constructionCategory' => 'cost',
                'audit' => ['createdBy' => '68', 'modifiedBy' => '68'],
                'disallowDirectPosting' => true,
                'enableGLMatching' => false,
                'reconciliationSequence' => null,
                'href' => '/objects/general-ledger/account/356',
            ],
            'ia::meta' => ['totalCount' => 1, 'totalSuccess' => 1, 'totalError' => 0],
        ]));

        $account = $accounts->get(new ObjectKey('356'));

        self::assertSame('356', $account->key->value);
        self::assertSame('1501', $account->id->value);
        self::assertSame('Vehicle Spare parts - Transmission', $account->name);
        self::assertSame(RecordStatus::Active, $account->status);
        self::assertSame(AccountType::BalanceSheet, $account->accountType);
        self::assertSame(NormalBalance::Debit, $account->normalBalance);
        self::assertSame(ClosingType::NonClosingAccount, $account->closingType);
        self::assertNull($account->closeToGLAccount);
        self::assertSame(AlternativeGLAccount::None, $account->alternativeGLAccount);
        self::assertSame(ConstructionCategory::Cost, $account->constructionCategory);
        self::assertNull($account->category);
        self::assertNull($account->taxCode);
        self::assertTrue($account->disallowDirectPosting);
        self::assertFalse($account->enableGLMatching);
        self::assertFalse($account->isTaxable);
        self::assertNull($account->isGermanTaxAccount);
        self::assertTrue($account->requireDimensions?->department);
        self::assertFalse($account->requireDimensions->class);
        self::assertNull($account->requireDimensions->task);
        self::assertSame(['department', 'project'], $account->requireDimensions->required());
        self::assertSame('GET', $http->requests[0]->getMethod());
        self::assertSame('https://api.intacct.com/ia/api/v1/objects/general-ledger/account/356', $this->url($http->requests[0]));
    }

    public function test_query_rows_with_dotted_keys_populate_nested_fields(): void
    {
        [$accounts, $http] = $this->accounts($this->json([
            'ia::result' => [[
                'key' => '400',
                'id' => '3900',
                'name' => 'Retained Earnings',
                'status' => 'inactive',
                'accountType' => 'incomeStatement',
                'normalBalance' => 'credit',
                'closingType' => 'closingAccount',
                'closeToGLAccount.key' => '12',
                'closeToGLAccount.id' => '3000',
                'alternativeGLAccount' => 'payablesAccount',
                'constructionCategory' => null,
                'requireDimensions.location' => true,
                'requireDimensions.task' => false,
                'entity.key' => null,
                'entity.id' => null,
                'entity.name' => null,
            ]],
            'ia::meta' => ['totalCount' => 1, 'start' => 1, 'pageSize' => 100, 'next' => null],
        ]));

        $account = $accounts->query()->items[0];

        self::assertSame(RecordStatus::Inactive, $account->status);
        self::assertSame(AccountType::IncomeStatement, $account->accountType);
        self::assertSame(NormalBalance::Credit, $account->normalBalance);
        self::assertSame(ClosingType::ClosingAccount, $account->closingType);
        self::assertSame('3000', $account->closeToGLAccount?->id?->value);
        self::assertSame('12', $account->closeToGLAccount->key?->value);
        self::assertSame(AlternativeGLAccount::PayablesAccount, $account->alternativeGLAccount);
        self::assertNull($account->constructionCategory);
        self::assertSame(['location'], $account->requireDimensions?->required());
        self::assertNull($account->entity);

        self::assertSame('https://api.intacct.com/ia/api/v1/services/core/query', $this->url($http->requests[0]));
        $body = $this->jsonBody($http->requests[0]);
        self::assertSame('general-ledger/account', $body['object']);
        self::assertIsArray($body['fields']);
        self::assertContains('closeToGLAccount.id', $body['fields']);
        self::assertContains('requireDimensions.department', $body['fields']);
        self::assertContains('entity.id', $body['fields']);
    }

    public function test_the_accounts_client_exposes_no_write_methods(): void
    {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(AccountsClient::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        sort($methods);

        self::assertSame(['__construct', 'get', 'query'], $methods);
    }

    /** @return array{AccountsClient, QueueHttpClient} */
    private function accounts(Response ...$responses): array
    {
        $http = new QueueHttpClient(...$responses);
        $factory = new HttpFactory;
        $transport = new ApiTransport(
            new StaticAccessTokenProvider(new AccessToken('token')),
            $http,
            $factory,
            $factory,
        );

        return [(new GeneralLedger($transport, new QueryClient($transport)))->accounts, $http];
    }
}
