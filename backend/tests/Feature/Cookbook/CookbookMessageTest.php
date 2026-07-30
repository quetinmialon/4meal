<?php

use App\Models\Cookbook;
use App\Models\CookbookMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function cookbookMessageToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function messageCookbook(User $owner): Cookbook
{
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    return $cookbook;
}

it('allows a member to send a message and denies an external user', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);

    $this->withToken(cookbookMessageToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Bonjour !'])
        ->assertCreated()
        ->assertJsonPath('data.content', 'Bonjour !')
        ->assertJsonPath('data.author.id', $owner->id);

    $this->withToken(cookbookMessageToken($external))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Intrus'])
        ->assertForbidden();
});

it('validates message length and trims surrounding whitespace', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);
    $token = cookbookMessageToken($owner);

    $this->withToken($token)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => '   '])
        ->assertUnprocessable();

    $this->withToken($token)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => str_repeat('a', 2001)])
        ->assertUnprocessable();

    $this->withToken($token)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => '  Conservé  '])
        ->assertCreated()
        ->assertJsonPath('data.content', 'Conservé');
});

it('returns cookbook history in chronological cursor pages', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);
    $token = cookbookMessageToken($owner);

    foreach (['Premier', 'Deuxième', 'Troisième'] as $content) {
        $this->withToken($token)->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', compact('content'))
            ->assertCreated();
    }

    $firstPage = $this->withToken($token)
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/messages?per_page=2')
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Premier')
        ->assertJsonPath('data.1.content', 'Deuxième')
        ->assertJsonPath('meta.pagination.per_page', 2);

    $cursor = $firstPage->json('meta.pagination.next_cursor');
    expect($cursor)->toBeString()->not->toBeEmpty();

    $this->withToken($token)
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/messages?per_page=2&cursor='.urlencode($cursor))
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Troisième')
        ->assertJsonCount(1, 'data');
});

it('returns only the three latest messages with sender details', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123', 'avatar_path' => 'avatars/member.png']);
    $cookbook = messageCookbook($owner);
    $cookbook->members()->attach($member, ['role' => 'commenter']);
    $token = cookbookMessageToken($member);

    foreach (['Un', 'Deux', 'Trois', 'Quatre'] as $content) {
        CookbookMessage::query()->create([
            'cookbook_id' => $cookbook->id,
            'user_id' => $member->id,
            'content' => $content,
        ]);
    }

    $this->withToken($token)
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/messages/latest')
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Deux')
        ->assertJsonPath('data.2.content', 'Quatre')
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.author.name', $member->name)
        ->assertJsonPath('data.0.author.avatar_url', 'http://localhost/storage/avatars/member.png')
        ->assertJsonPath('data.0.author.role', 'commenter');
});

it('protects history access and throttles message creation', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $external = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);
    $ownerToken = cookbookMessageToken($owner);

    $this->withToken(cookbookMessageToken($external))
        ->getJson('/api/cookbooks/'.$cookbook->public_id.'/messages')
        ->assertForbidden();

    for ($i = 0; $i < 10; $i++) {
        $this->withToken($ownerToken)
            ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Message '.$i])
            ->assertCreated();
    }

    $this->withToken($ownerToken)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Trop tard'])
        ->assertStatus(429);

    expect(CookbookMessage::query()->count())->toBe(10);
});

it('allows moderators to edit their own messages and moderate other messages', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $moderator = User::factory()->create(['password' => 'password123']);
    $author = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);
    $cookbook->members()->attach($moderator, ['role' => 'moderator']);
    $cookbook->members()->attach($author, ['role' => 'commenter']);
    $ownMessage = CookbookMessage::query()->create(['cookbook_id' => $cookbook->id, 'user_id' => $moderator->id, 'content' => 'A modifier']);
    $otherMessage = CookbookMessage::query()->create(['cookbook_id' => $cookbook->id, 'user_id' => $author->id, 'content' => 'A moderer']);

    $this->withToken(cookbookMessageToken($moderator))
        ->patchJson('/api/cookbooks/'.$cookbook->public_id.'/messages/'.$ownMessage->public_id, ['content' => 'Modifié'])
        ->assertOk()
        ->assertJsonPath('data.content', 'Modifié')
        ->assertJsonPath('data.edited_at', fn ($value): bool => is_string($value));

    $this->withToken(cookbookMessageToken($moderator))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/messages/'.$otherMessage->public_id)
        ->assertOk()
        ->assertJsonPath('data.content', 'Message supprimé par '.$moderator->name)
        ->assertJsonPath('data.is_deleted', true)
        ->assertJsonPath('data.deleted_by.name', $moderator->name);
});

it('prevents regular members from moderating other messages and lets the owner moderate', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $member = User::factory()->create(['password' => 'password123']);
    $author = User::factory()->create(['password' => 'password123']);
    $cookbook = messageCookbook($owner);
    $cookbook->members()->attach($member, ['role' => 'commenter']);
    $cookbook->members()->attach($author, ['role' => 'commenter']);
    $message = CookbookMessage::query()->create(['cookbook_id' => $cookbook->id, 'user_id' => $author->id, 'content' => 'Original']);

    $this->withToken(cookbookMessageToken($member))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/messages/'.$message->public_id)
        ->assertForbidden();

    $this->withToken(cookbookMessageToken($owner))
        ->deleteJson('/api/cookbooks/'.$cookbook->public_id.'/messages/'.$message->public_id)
        ->assertOk()
        ->assertJsonPath('data.deleted_by.name', $owner->name);
});
