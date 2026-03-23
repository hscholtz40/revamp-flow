<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductSerialNumber;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\JobcardPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotePolicy;
use App\Policies\ReportTemplatePolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class TenantAuthorizationPoliciesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function userWithCompany(int $companyId): User
    {
        $company = new Company;
        $company->forceFill(['id' => $companyId, 'is_active' => true]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with($companyId)->andReturn(true);
        $user->shouldReceive('isLimitedUser')->andReturn(false);
        $user->shouldReceive('hasModulePermission')->zeroOrMoreTimes()->andReturn(true);

        return $user;
    }

    public function test_invoice_policy_allows_when_company_matches(): void
    {
        $user = $this->userWithCompany(1);
        $invoice = new Invoice(['company_id' => 1]);
        $policy = new InvoicePolicy;

        $this->assertTrue($policy->view($user, $invoice));
    }

    public function test_invoice_policy_denies_when_company_differs(): void
    {
        $user = $this->userWithCompany(1);
        $invoice = new Invoice(['company_id' => 2]);
        $policy = new InvoicePolicy;

        $this->assertFalse($policy->view($user, $invoice));
    }

    public function test_invoice_policy_denies_without_invoices_view_permission(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 1, 'is_active' => true]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with(1)->andReturn(true);
        $user->shouldReceive('isLimitedUser')->andReturn(false);
        $user->shouldReceive('hasModulePermission')->with('invoices', 'view')->andReturn(false);

        $invoice = new Invoice(['company_id' => 1]);
        $policy = new InvoicePolicy;

        $this->assertFalse($policy->view($user, $invoice));
    }

    public function test_quote_policy_denies_without_quotes_create_permission(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 1, 'is_active' => true]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with(1)->andReturn(true);
        $user->shouldReceive('isLimitedUser')->andReturn(false);
        $user->shouldReceive('hasModulePermission')->with('quotes', 'create')->andReturn(false);

        $policy = new QuotePolicy;

        $this->assertFalse($policy->create($user));
    }

    public function test_report_template_with_null_company_id_is_viewable_in_tenant_context(): void
    {
        $user = $this->userWithCompany(5);
        $template = new ReportTemplate(['company_id' => null]);
        $policy = new ReportTemplatePolicy;

        $this->assertTrue($policy->view($user, $template));
    }

    public function test_jobcard_policy_allows_standard_user_for_tenant_jobcard(): void
    {
        $user = $this->userWithCompany(1);
        $jobcard = new Jobcard([
            'company_id' => 1,
            'assigned_to_user_id' => null,
            'assigned_to_team_id' => null,
        ]);
        $policy = new JobcardPolicy;

        $this->assertTrue($policy->view($user, $jobcard));
    }

    public function test_product_policy_manage_batch_requires_matching_product_id(): void
    {
        $user = $this->userWithCompany(3);
        $product = new Product(['company_id' => 3, 'id' => 100]);
        $batch = new ProductBatch(['company_id' => 3, 'product_id' => 200]);
        $policy = new ProductPolicy;

        $this->assertFalse($policy->manageBatch($user, $product, $batch));
    }

    public function test_product_policy_manage_serial_allows_when_product_matches(): void
    {
        $user = $this->userWithCompany(2);
        $product = new Product;
        $product->forceFill(['id' => 50, 'company_id' => 2]);
        $serial = new ProductSerialNumber;
        $serial->forceFill(['company_id' => 2, 'product_id' => 50]);
        $policy = new ProductPolicy;

        $this->assertTrue($policy->manageSerial($user, $product, $serial));
    }
}
