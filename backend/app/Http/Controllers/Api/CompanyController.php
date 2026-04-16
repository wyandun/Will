<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService) {}

    /**
     * List companies visible to the authenticated user.
     *
     * GET /api/v1/companies
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companyService->list($request->user());

        return CompanyResource::collection($companies);
    }

    /**
     * Create a new company (internal/superadmin direct route).
     *
     * POST /api/v1/companies
     *
     * NOTE: This is an internal route for superadmin use. The canonical flow used
     * by the frontend is POST /api/v1/companies/close-deal (closeDeal()), which
     * also creates the two required BPMN process maps in the same transaction.
     * Use store() only when creating a company record in isolation.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new CompanyResource($company),
            'message' => 'Empresa creada correctamente.',
        ], 201);
    }

    /**
     * Return a single company.
     *
     * GET /api/v1/companies/{company}
     */
    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        $company->loadMissing('franchise');

        return response()->json([
            'success' => true,
            'data'    => new CompanyResource($company),
            'message' => 'OK.',
        ]);
    }

    /**
     * Update an existing company (PATCH semantics — all fields optional).
     *
     * PUT/PATCH /api/v1/companies/{company}
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company = $this->companyService->update($company, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new CompanyResource($company),
            'message' => 'Empresa actualizada correctamente.',
        ]);
    }

    /**
     * Delete a company.
     *
     * DELETE /api/v1/companies/{company}
     */
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->companyService->delete($company);

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }

    /**
     * "Close Deal" — creates a company plus its two mandatory process maps
     * inside a single DB transaction.
     *
     * POST /api/v1/companies/close-deal
     */
    public function closeDeal(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->closeDeal($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new CompanyResource($company),
            'message' => 'Deal cerrado correctamente. Empresa y mapas de proceso creados.',
        ], 201);
    }
}
