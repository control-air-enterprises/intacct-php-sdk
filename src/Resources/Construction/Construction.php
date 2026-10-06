<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction;

use ControlAir\Intacct\Core\Http\ApiTransport;
use ControlAir\Intacct\Core\Query\QueryClient;
use ControlAir\Intacct\Resources\Construction\ChangeRequests\ChangeRequestsClient;
use ControlAir\Intacct\Resources\Construction\CostTypes\CostTypesClient;
use ControlAir\Intacct\Resources\Construction\EmployeePositions\EmployeePositionsClient;
use ControlAir\Intacct\Resources\Construction\LaborClasses\LaborClassesClient;
use ControlAir\Intacct\Resources\Construction\LaborShifts\LaborShiftsClient;
use ControlAir\Intacct\Resources\Construction\LaborUnions\LaborUnionsClient;
use ControlAir\Intacct\Resources\Construction\ProjectChangeOrders\ProjectChangeOrdersClient;
use ControlAir\Intacct\Resources\Construction\ProjectContractLines\ProjectContractLinesClient;
use ControlAir\Intacct\Resources\Construction\ProjectContracts\ProjectContractsClient;

final readonly class Construction
{
    public CostTypesClient $costTypes;

    public LaborUnionsClient $laborUnions;

    public LaborClassesClient $laborClasses;

    public LaborShiftsClient $laborShifts;

    public EmployeePositionsClient $employeePositions;

    public ProjectContractsClient $projectContracts;

    public ProjectContractLinesClient $projectContractLines;

    public ProjectChangeOrdersClient $projectChangeOrders;

    public ChangeRequestsClient $changeRequests;

    public function __construct(ApiTransport $transport, QueryClient $queries)
    {
        $this->costTypes = new CostTypesClient($transport, $queries);
        $this->laborUnions = new LaborUnionsClient($transport, $queries);
        $this->laborClasses = new LaborClassesClient($transport, $queries);
        $this->laborShifts = new LaborShiftsClient($transport, $queries);
        $this->employeePositions = new EmployeePositionsClient($transport, $queries);
        $this->projectContracts = new ProjectContractsClient($transport, $queries);
        $this->projectContractLines = new ProjectContractLinesClient($transport, $queries);
        $this->projectChangeOrders = new ProjectChangeOrdersClient($transport, $queries);
        $this->changeRequests = new ChangeRequestsClient($transport, $queries);
    }
}
