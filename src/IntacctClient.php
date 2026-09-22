<?php

declare(strict_types=1);

namespace ControlAir\Intacct;

use ControlAir\Intacct\Auth\Contracts\AccessTokenProvider;
use ControlAir\Intacct\Configuration\ApiConfiguration;
use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Model\ModelClient;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\AccountsPayable\AccountsPayable;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentFoldersClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Attachments\AttachmentsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Classes\ClassesClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Contacts\ContactsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Departments\DepartmentsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Dimensions\DimensionsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Employees\EmployeesClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Entities\EntitiesClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Locations\LocationsClient;
use ControlAir\Intacct\Resources\CompanyConfiguration\Users\UsersClient;
use ControlAir\Intacct\Resources\Construction\CostTypes\CostTypesClient;
use ControlAir\Intacct\Resources\GeneralLedger\GeneralLedger;
use ControlAir\Intacct\Resources\InventoryControl\InventoryControl;
use ControlAir\Intacct\Resources\Projects\ProjectResources\ProjectResourcesClient;
use ControlAir\Intacct\Resources\Projects\ProjectsClient;
use ControlAir\Intacct\Resources\Projects\Tasks\TasksClient;
use ControlAir\Intacct\Resources\Purchasing\Purchasing;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class IntacctClient
{
    public ApiTransport $transport;

    public QueryClient $queries;

    public ModelClient $model;

    public ProjectsClient $projects;

    public TasksClient $tasks;

    public CostTypesClient $costTypes;

    public ProjectResourcesClient $projectResources;

    public DimensionsClient $dimensions;

    public EmployeesClient $employees;

    public ClassesClient $classes;

    public DepartmentsClient $departments;

    public LocationsClient $locations;

    public EntitiesClient $entities;

    public UsersClient $users;

    public ContactsClient $contacts;

    public AttachmentsClient $attachments;

    public AttachmentFoldersClient $attachmentFolders;

    public AccountsPayable $accountsPayable;

    public GeneralLedger $generalLedger;

    public InventoryControl $inventory;

    public Purchasing $purchasing;

    public function __construct(
        AccessTokenProvider $tokens,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ApiConfiguration $configuration = new ApiConfiguration,
    ) {
        $this->transport = new ApiTransport(
            $tokens,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $configuration,
        );
        $this->queries = new QueryClient($this->transport);
        $this->model = new ModelClient($this->transport);
        $this->projects = new ProjectsClient($this->transport, $this->queries);
        $this->tasks = new TasksClient($this->transport, $this->queries);
        $this->costTypes = new CostTypesClient($this->transport, $this->queries);
        $this->projectResources = new ProjectResourcesClient($this->transport, $this->queries);
        $this->dimensions = new DimensionsClient($this->transport);
        $this->employees = new EmployeesClient($this->transport, $this->queries);
        $this->classes = new ClassesClient($this->transport, $this->queries);
        $this->departments = new DepartmentsClient($this->transport, $this->queries);
        $this->locations = new LocationsClient($this->transport, $this->queries);
        $this->entities = new EntitiesClient($this->transport, $this->queries);
        $this->users = new UsersClient($this->transport, $this->queries);
        $this->contacts = new ContactsClient($this->transport, $this->queries);
        $this->attachments = new AttachmentsClient($this->transport, $this->queries);
        $this->attachmentFolders = new AttachmentFoldersClient($this->transport, $this->queries);
        $this->accountsPayable = new AccountsPayable($this->transport, $this->queries);
        $this->generalLedger = new GeneralLedger($this->transport, $this->queries);
        $this->inventory = new InventoryControl($this->transport, $this->queries);
        $this->purchasing = new Purchasing($this->transport, $this->queries);
    }
}
