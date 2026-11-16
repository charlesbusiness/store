<?php

namespace Tests\Feature\Api;

use App\Enums\ReportingMode;
use App\Enums\StockManagementMode;
use App\Models\Branch;
use App\Models\PasswordResetOtp;
use App\Models\Tenant;
use App\Models\User;
use App\Models\MemberInvitationToken;
use App\Notifications\MemberAddedNotification;
use App\Notifications\MemberInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TenantMemberTest extends TestCase
{
    use RefreshDatabase;

    private function account(): array
    {
        $tenant = Tenant::create(['name' => 'Account', 'stock_management_mode' => StockManagementMode::Account, 'reporting_mode' => ReportingMode::Account]);
        $main = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main', 'code' => 'main', 'is_default' => true, 'is_active' => true]);
        $second = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Second', 'code' => 'second', 'is_active' => true]);
        $owner = User::factory()->create();
        $tenant->users()->attach($owner, ['role' => 'owner', 'is_active' => true]);
        $main->users()->attach($owner);

        return [$tenant, $main, $second, $owner];
    }

    public function test_owner_creates_a_member_with_an_invite_and_a_branch(): void
    {
        Notification::fake();
        [$tenant, $main, $second, $owner] = $this->account();

        $response = $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'New Member',
                'email' => 'New.Member@example.com',
                'role' => 'member',
                'branch_ids' => [$main->id, $second->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'new.member@example.com')
            ->assertJsonCount(2, 'data.branch_ids');

        $member = User::where('email', 'new.member@example.com')->firstOrFail();
        $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $member->id, 'role' => 'member', 'is_active' => 1]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $second->id, 'user_id' => $member->id]);
        $this->assertDatabaseCount('member_invitation_tokens', 1);
        Notification::assertSentOnDemand(MemberInvitationNotification::class);
    }

    public function test_member_creation_requires_at_least_one_branch(): void
    {
        [$tenant, $main, , $owner] = $this->account();

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'No Branch',
                'email' => 'no.branch@example.com',
                'role' => 'member',
                'branch_ids' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_ids']);
    }

    public function test_existing_free_user_is_attached_and_notified(): void
    {
        Notification::fake();
        [$tenant, $main, $second, $owner] = $this->account();
        $existing = User::factory()->create(['email' => 'existing@example.com']);

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'Existing User',
                'email' => 'existing@example.com',
                'role' => 'member',
                'branch_ids' => [$second->id],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $existing->id]);
        $this->assertDatabaseHas('branch_user', ['branch_id' => $second->id, 'user_id' => $existing->id]);
        Notification::assertSentTo($existing, MemberAddedNotification::class);
        $this->assertDatabaseMissing('password_reset_otps', ['email' => 'existing@example.com']);
    }

    public function test_member_can_accept_invitation_and_create_a_password(): void
    {
        Notification::fake();
        [$tenant, $main, , $owner] = $this->account();

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'Invited User',
                'email' => 'invited@example.com',
                'role' => 'member',
                'branch_ids' => [$main->id],
            ])
            ->assertCreated();

        $invitation = MemberInvitationToken::firstOrFail();
        $notifications = Notification::getFacadeRoot()->sentNotifications();
        $token = collect($notifications)->flatten(5)->first(fn ($item) => $item instanceof MemberInvitationNotification);
        $this->assertNotNull($token);
        preg_match('/accept-invite\?token=([^"&]+)/', $token->toMail((object) [])->render(), $matches);
        $this->assertNotEmpty($matches[1]);

        $this->postJson('/api/auth/accept-invite', [
            'token' => $matches[1],
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'invited@example.com',
        ]);
        $this->assertNotNull($invitation->fresh()->used_at);
    }

    public function test_user_from_another_account_cannot_be_added(): void
    {
        [$tenant, $main, , $owner] = $this->account();
        $otherTenant = Tenant::factory()->create();
        $foreign = User::factory()->create(['email' => 'foreign@example.com']);
        $otherTenant->users()->attach($foreign, ['role' => 'member', 'is_active' => true]);

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'Foreign',
                'email' => 'foreign@example.com',
                'role' => 'member',
                'branch_ids' => [$main->id],
            ])
            ->assertUnprocessable();
    }

    public function test_owner_and_admin_can_assign_branches_but_only_owner_changes_roles(): void
    {
        [$tenant, $main, $second, $owner] = $this->account();
        $admin = $this->memberOf($tenant, [$main->id], 'admin');
        $member = $this->memberOf($tenant, [$main->id], 'member');

        $this->withHeaders($this->withTenant($admin, $tenant, $main))
            ->putJson("/api/tenant/members/{$member->id}/branches", ['branch_ids' => [$second->id]])
            ->assertOk()
            ->assertJsonPath('data.branch_ids', [$second->id]);

        $this->withHeaders($this->withTenant($admin, $tenant, $main))
            ->patchJson("/api/tenant/members/{$member->id}", ['role' => 'admin'])
            ->assertForbidden();

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->patchJson("/api/tenant/members/{$member->id}", ['role' => 'admin'])
            ->assertOk();
    }

    public function test_a_member_cannot_remove_their_last_branch(): void
    {
        [$tenant, $main, , $owner] = $this->account();
        $member = $this->memberOf($tenant, [$main->id], 'member');

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->putJson("/api/tenant/members/{$member->id}/branches", ['branch_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_ids']);
    }

    public function test_disabling_a_branch_that_would_orphan_a_member_is_blocked(): void
    {
        [$tenant, $main, $second, $owner] = $this->account();
        $this->memberOf($tenant, [$second->id], 'member');

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->deleteJson("/api/branches/{$second->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('branches', ['id' => $second->id, 'is_active' => 1]);
    }

    public function test_disabling_a_branch_detaches_assignments_when_nobody_is_orphaned(): void
    {
        [$tenant, $main, $second, $owner] = $this->account();
        $member = $this->memberOf($tenant, [$main->id, $second->id], 'member');

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->deleteJson("/api/branches/{$second->id}")
            ->assertOk();

        $this->assertDatabaseMissing('branch_user', ['branch_id' => $second->id, 'user_id' => $member->id]);
    }

    public function test_demoting_an_admin_without_branches_is_blocked(): void
    {
        [$tenant, $main, , $owner] = $this->account();
        $admin = $this->memberOf($tenant, [], 'admin');

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->patchJson("/api/tenant/members/{$admin->id}", ['role' => 'member'])
            ->assertUnprocessable();
    }

    public function test_owner_can_resend_an_invite(): void
    {
        Notification::fake();
        [$tenant, $main, , $owner] = $this->account();
        $member = $this->memberOf($tenant, [$main->id], 'member');

        $this->withHeaders($this->withTenant($owner, $tenant, $main))
            ->postJson("/api/tenant/members/{$member->id}/invite")
            ->assertOk();

        $this->assertDatabaseCount('member_invitation_tokens', 1);
        Notification::assertSentOnDemand(MemberInvitationNotification::class);
    }

    public function test_members_cannot_create_members(): void
    {
        [$tenant, $main, , $owner] = $this->account();
        $member = $this->memberOf($tenant, [$main->id], 'member');

        $this->withHeaders($this->withTenant($member, $tenant, $main))
            ->postJson('/api/tenant/members', [
                'name' => 'Nope',
                'email' => 'nope@example.com',
                'role' => 'member',
                'branch_ids' => [$main->id],
            ])
            ->assertForbidden();
    }
}
