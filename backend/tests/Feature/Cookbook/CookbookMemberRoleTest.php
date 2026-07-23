<?php

use App\Models\Cookbook;
use App\Models\User;
use App\Services\Cookbook\ChangeCookbookMemberRoleAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

uses(RefreshDatabase::class);

function memberRoleToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

it('allows only owners to change a member role for every required actor role', function (string $actorRole, int $expectedStatus): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $actor = $actorRole === 'owner' ? $owner : User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    if ($actor !== $owner) {
        $cookbook->members()->attach($actor, ['role' => $actorRole]);
    }
    $cookbook->members()->attach($target, ['role' => 'reader']);

    $this->withToken(memberRoleToken($actor))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$target->id.'/role', ['role' => 'commenter'])
        ->assertStatus($expectedStatus);

    $this->assertDatabaseHas('cookbook_members', [
        'cookbook_id' => $cookbook->id,
        'user_id' => $target->id,
        'role' => $expectedStatus === 200 ? 'commenter' : 'reader',
    ]);
})->with([
    'owner' => ['owner', 200],
    'editor' => ['editor', 403],
    'reader' => ['reader', 403],
    'commenter' => ['commenter', 403],
]);

it('changes a member role and returns the updated membership', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($target, ['role' => 'reader']);

    $this->withToken(memberRoleToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$target->id.'/role', ['role' => 'commenter'])
        ->assertOk()
        ->assertJsonPath('data.role', 'commenter')
        ->assertJsonPath('data.user.id', $target->id);
});

it('transfers ownership atomically and turns the previous owner into an editor', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($target, ['role' => 'reader']);

    $this->withToken(memberRoleToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$target->id.'/role', ['role' => 'owner'])
        ->assertOk()
        ->assertJsonPath('data.role', 'owner');

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id, 'owner_id' => $target->id]);
    $this->assertDatabaseHas('cookbook_members', ['cookbook_id' => $cookbook->id, 'user_id' => $owner->id, 'role' => 'editor']);
    $this->assertDatabaseHas('cookbook_members', ['cookbook_id' => $cookbook->id, 'user_id' => $target->id, 'role' => 'owner']);
});

it('rejects roles outside the required role set', function (string $role): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($target, ['role' => 'reader']);

    $this->withToken(memberRoleToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$target->id.'/role', ['role' => $role])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');
})->with(['viewer', 'admin', '']);

it('does not allow the cookbook owner to be downgraded', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(memberRoleToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$owner->id.'/role', ['role' => 'editor'])
        ->assertStatus(409);

    $this->assertDatabaseHas('cookbook_members', ['cookbook_id' => $cookbook->id, 'user_id' => $owner->id, 'role' => 'owner']);
});

it('does not allow an owner to modify their own role incoherently', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(memberRoleToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/members/'.$owner->id.'/role', ['role' => 'owner'])
        ->assertOk();
});

it('does not remove the last remaining owner', function (): void {
    $actor = User::factory()->create(['password' => 'password123']);
    $target = User::factory()->create(['password' => 'password123']);
    $canonicalOwner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Permissions', 'owner_id' => $canonicalOwner->id]);
    $cookbook->members()->attach($target, ['role' => 'owner']);

    expect(fn () => app(ChangeCookbookMemberRoleAction::class)->execute($cookbook, $actor, $target, 'reader'))
        ->toThrow(ConflictHttpException::class);

    $this->assertDatabaseHas('cookbook_members', [
        'cookbook_id' => $cookbook->id,
        'user_id' => $target->id,
        'role' => 'owner',
    ]);
});
