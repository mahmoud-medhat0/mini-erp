<?php

namespace App\Domain\Tenant;

use App\Models\Branch;
use App\Models\Company;

class TenantContext
{
    public function __construct(
        private ?Company $company = null,
        private ?Branch $branch = null,
    ) {}

    public function set(?Company $company, ?Branch $branch): void
    {
        $this->company = $company;
        $this->branch = $branch;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function companyId(): ?string
    {
        return $this->company?->id;
    }

    public function branchId(): ?string
    {
        return $this->branch?->id;
    }

    /**
     * @return array{company: array<string, mixed>|null, branch: array<string, mixed>|null}
     */
    public function toSharedArray(): array
    {
        return [
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'base_currency' => $this->company->base_currency,
            ] : null,
            'branch' => $this->branch ? [
                'id' => $this->branch->id,
                'company_id' => $this->branch->company_id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
                'is_active' => $this->branch->is_active,
            ] : null,
        ];
    }
}
