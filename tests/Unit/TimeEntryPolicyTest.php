<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\TimeEntryPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class TimeEntryPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_limited_user_cannot_delete_own_or_any_time_entry(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 1]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isLimitedUser')->andReturn(true);
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with(1)->andReturn(true);

        $entry = new TimeEntry;
        $entry->forceFill(['company_id' => 1, 'user_id' => 10]);

        $policy = new TimeEntryPolicy;
        $this->assertFalse($policy->delete($user, $entry));
    }

    public function test_limited_user_cannot_update_another_users_entry(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 1]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->shouldReceive('isLimitedUser')->andReturn(true);
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with(1)->andReturn(true);

        $entry = new TimeEntry;
        $entry->forceFill(['company_id' => 1, 'user_id' => 99]);

        $policy = new TimeEntryPolicy;
        $this->assertFalse($policy->update($user, $entry));
    }

    public function test_non_limited_user_may_delete_tenant_time_entry(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 1]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isLimitedUser')->andReturn(false);
        $user->shouldReceive('getCurrentCompany')->andReturn($company);
        $user->shouldReceive('hasAccessToCompany')->with(1)->andReturn(true);

        $entry = new TimeEntry;
        $entry->forceFill(['company_id' => 1, 'user_id' => 50]);

        $policy = new TimeEntryPolicy;
        $this->assertTrue($policy->delete($user, $entry));
    }
}
