<?php

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function cookbookToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    $response = test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $response->assertOk();

    return $response->json('data.access_token');
}

it('creates a cookbook with its owner membership in one operation', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $response = $this->withToken(cookbookToken($user))
        ->postJson('/api/cookbooks', ['name' => 'Mes recettes']);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Mes recettes')
        ->assertJsonPath('data.owner.id', $user->id);

    $publicId = $response->json('data.id');

    expect($publicId)->toBeString()->not->toBe((string) Cookbook::query()->firstOrFail()->id);
    $this->assertDatabaseHas('cookbooks', ['public_id' => $publicId, 'owner_id' => $user->id]);
    $this->assertDatabaseHas('cookbook_members', [
        'cookbook_id' => Cookbook::query()->where('public_id', $publicId)->value('id'),
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

it('creates cookbooks with the MCD attributes and unique generated slugs', function () {
    Storage::fake('public');
    $user = User::factory()->create(['password' => 'password123']);
    $token = cookbookToken($user);
    $payload = [
        'name' => 'Cuisine du soir',
        'description' => 'Mes recettes du quotidien',
    ];

    $first = $this->withToken($token)->post('/api/cookbooks', [
        ...$payload,
        'image' => UploadedFile::fake()->create('cuisine.jpg', 100, 'image/jpeg'),
    ])->assertCreated();
    $second = $this->withToken($token)->post('/api/cookbooks', [
        ...$payload,
        'image' => UploadedFile::fake()->create('cuisine-2.jpg', 100, 'image/jpeg'),
    ])->assertCreated();

    expect($first->json('data.slug'))->toBe('cuisine-du-soir')
        ->and($second->json('data.slug'))->toBe('cuisine-du-soir-2')
        ->and($first->json('data.image_path'))->toStartWith('cookbooks/');

    Storage::disk('public')->assertExists($first->json('data.image_path'));

    $this->assertDatabaseHas('cookbooks', [
        'name' => $payload['name'],
        'description' => $payload['description'],
    ]);
});

it('requires a non-empty cookbook name', function () {
    $user = User::factory()->create(['password' => 'password123']);

    $this->withToken(cookbookToken($user))
        ->postJson('/api/cookbooks', ['name' => ' '])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonStructure(['error' => ['details' => ['fields' => ['name']]]]);
});

it('allows a member to access a cookbook and denies unrelated users', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $otherUser = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Partagé', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(cookbookToken($owner))
        ->getJson('/api/cookbooks/'.$cookbook->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $cookbook->public_id);

    $this->withToken(cookbookToken($otherUser))
        ->getJson('/api/cookbooks/'.$cookbook->public_id)
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');
});

it('lists only cookbooks accessible to the authenticated user with their role', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $memberCookbook = Cookbook::query()->create(['name' => 'Accessible', 'owner_id' => $user->id]);
    $memberCookbook->members()->attach($user, ['role' => 'editor']);
    $otherCookbook = Cookbook::query()->create(['name' => 'Private', 'owner_id' => User::factory()->create()->id]);

    $response = $this->withToken(cookbookToken($user))
        ->getJson('/api/cookbooks?per_page=10');

    $response
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 10)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $memberCookbook->public_id)
        ->assertJsonPath('data.0.member_role', 'editor');

    expect($response->json('data.0.id'))->not->toBe($otherCookbook->public_id);
});

it('returns the authenticated member role when viewing a cookbook', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Accessible', 'owner_id' => $user->id]);
    $cookbook->members()->attach($user, ['role' => 'reader']);

    $this->withToken(cookbookToken($user))
        ->getJson('/api/cookbooks/'.$cookbook->public_id)
        ->assertOk()
        ->assertJsonPath('data.member_role', 'reader');
});

it('lists paginated cookbook members with their role, arrival date and active status', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Equipe', 'owner_id' => $owner->id]);
    $ownerJoinedAt = now()->subDays(2);
    $memberJoinedAt = now()->subDay();
    $cookbook->members()->attach($owner, ['role' => 'owner', 'joined_at' => $ownerJoinedAt]);
    $cookbook->members()->attach($member, ['role' => 'reader', 'joined_at' => $memberJoinedAt]);

    $response = $this->withToken(cookbookToken($owner))
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/members?per_page=1&page=2');

    $response
        ->assertOk()
        ->assertJsonPath('meta.pagination.current_page', 2)
        ->assertJsonPath('meta.pagination.per_page', 1)
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.user.id', $member->id)
        ->assertJsonPath('data.0.user.email', $member->email)
        ->assertJsonPath('data.0.role', 'reader')
        ->assertJsonPath('data.0.joined_at', $memberJoinedAt->copy()->startOfSecond()->toJSON())
        ->assertJsonPath('data.0.status', 'active');
});

it('allows only cookbook members to list its members', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Privé', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner', 'joined_at' => now()]);
    $cookbook->members()->attach($member, ['role' => 'reader', 'joined_at' => now()]);

    $this->withToken(cookbookToken($member))
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/members')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken(cookbookToken($external))
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/members')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');
});

it('paginates recipes without exposing recipes from another cookbook', function () {
    $user = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Accessible', 'owner_id' => $user->id]);
    $cookbook->members()->attach($user, ['role' => 'owner']);
    $otherCookbook = Cookbook::query()->create([
        'name' => 'Other',
        'owner_id' => User::factory()->create()->id,
    ]);
    Recipe::query()->create(['cookbook_id' => $cookbook->id, 'name' => 'Visible']);
    Recipe::query()->create(['cookbook_id' => $cookbook->id, 'name' => 'Visible too']);
    Recipe::query()->create(['cookbook_id' => $otherCookbook->id, 'name' => 'Hidden']);

    $this->withToken(cookbookToken($user))
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/recipes?per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.pagination.per_page', 1)
        ->assertJsonMissing(['name' => 'Hidden']);
});

it('allows the owner to rename a cookbook', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Ancien nom', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(cookbookToken($owner))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id, ['name' => 'Nouveau nom'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nouveau nom')
        ->assertJsonPath('data.member_role', 'owner');

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id, 'name' => 'Nouveau nom']);
});

it('allows an editor to rename a cookbook', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Ancien nom', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($editor, ['role' => 'editor']);

    $this->withToken(cookbookToken($editor))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id, ['name' => 'Nom edite'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nom edite')
        ->assertJsonPath('data.member_role', 'editor');
});

it('rejects a reader from renaming a cookbook', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Ancien nom', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($reader, ['role' => 'reader']);

    $this->withToken(cookbookToken($reader))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id, ['name' => 'Interdit'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id, 'name' => 'Ancien nom']);
});

it('rejects an external user from renaming a cookbook', function () {
    $owner = User::factory()->create();
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Ancien nom', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(cookbookToken($external))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id, ['name' => 'Interdit'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id, 'name' => 'Ancien nom']);
});

it('allows only the owner to delete a cookbook after explicit confirmation', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $editor = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'A supprimer', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($editor, ['role' => 'editor']);
    $recipe = Recipe::query()->create(['cookbook_id' => $cookbook->id, 'name' => 'Recette liée']);

    $this->withToken(cookbookToken($editor))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id, ['confirmation' => $cookbook->name])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'authorization_error');

    $this->withToken(cookbookToken($owner))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id, ['confirmation' => $cookbook->name])
        ->assertNoContent();

    $this->assertDatabaseMissing('cookbooks', ['id' => $cookbook->id]);
    $this->assertDatabaseMissing('cookbook_members', ['cookbook_id' => $cookbook->id]);
    $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
});

it('rejects deletion without the exact cookbook name confirmation', function () {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Confirmation requise', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    $this->withToken(cookbookToken($owner))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id, ['confirmation' => 'mauvais nom'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_error');

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id]);
});

it('rejects a reader and an external user from deleting a cookbook', function () {
    $owner = User::factory()->create();
    $reader = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = Cookbook::query()->create(['name' => 'Protégé', 'owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);
    $cookbook->members()->attach($reader, ['role' => 'reader']);

    foreach ([$reader, $external] as $user) {
        $this->withToken(cookbookToken($user))
            ->deleteJson('/api/cookbooks/'.$cookbook->public_id, ['confirmation' => $cookbook->name])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'authorization_error');
    }

    $this->assertDatabaseHas('cookbooks', ['id' => $cookbook->id]);
});
