<?php

use App\Events\CookbookInvitationAccepted;
use App\Events\CookbookInvitationCreated;
use App\Events\CookbookInvitationDeclined;
use App\Events\CookbookMessageCreated;
use App\Events\RecipeCommentCreated;
use App\Events\RecipeCommentDeleted;
use App\Events\RecipeCommentUpdated;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function businessBroadcastingToken(User $user): string
{
    config()->set('jwt.secret', Str::repeat('a', 64));
    config()->set('jwt.issuer', 'http://localhost');
    config()->set('jwt.ttl', 900);

    return test()->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->json('data.access_token');
}

function businessBroadcastingCookbook(User $owner): Cookbook
{
    $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id]);
    $cookbook->members()->attach($owner, ['role' => 'owner']);

    return $cookbook;
}

it('broadcasts cookbook messages on the cookbook channel', function (): void {
    Event::fake([CookbookMessageCreated::class]);
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = businessBroadcastingCookbook($owner);

    $this->withToken(businessBroadcastingToken($owner))
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/messages', ['content' => 'Bonjour'])
        ->assertCreated();

    Event::assertDispatched(CookbookMessageCreated::class, function (CookbookMessageCreated $event) use ($cookbook): bool {
        return $event->broadcastOn()[0]->name === 'private-cookbook.'.$cookbook->public_id
            && $event->broadcastAs() === 'cookbook.message.created'
            && isset($event->broadcastWith()['message']['id']);
    });
});

it('broadcasts cookbook recipe comment changes with minimal payloads', function (): void {
    $owner = User::factory()->create(['password' => 'password123']);
    $cookbook = businessBroadcastingCookbook($owner);
    $recipe = Recipe::factory()->inCookbook($cookbook)->create();
    $token = businessBroadcastingToken($owner);

    Event::fake([RecipeCommentCreated::class]);
    $created = $this->withToken($token)
        ->postJson('/api/recipes/'.$recipe->public_id.'/comments', ['content' => 'Très bon'])
        ->assertCreated();
    $commentId = $created->json('data.id');

    Event::assertDispatched(RecipeCommentCreated::class, function (RecipeCommentCreated $event) use ($cookbook, $recipe): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastOn()[0]->name === 'private-cookbook.'.$cookbook->public_id
            && $payload['recipe']['id'] === $recipe->public_id;
    });

    Event::fake([RecipeCommentUpdated::class, RecipeCommentDeleted::class]);
    $this->withToken($token)
        ->patchJson('/api/recipes/'.$recipe->public_id.'/comments/'.$commentId, ['content' => 'Très bon !'])
        ->assertOk();
    $this->withToken($token)
        ->deleteJson('/api/recipes/'.$recipe->public_id.'/comments/'.$commentId)
        ->assertNoContent();

    Event::assertDispatched(RecipeCommentUpdated::class);
    Event::assertDispatched(RecipeCommentDeleted::class, function (RecipeCommentDeleted $event) use ($recipe, $commentId): bool {
        return $event->broadcastWith() === [
            'recipe' => ['id' => $recipe->public_id],
            'comment' => ['id' => $commentId],
        ];
    });
});

it('broadcasts invitations to the recipient and status changes to the inviter', function (): void {
    Mail::fake();
    $inviter = User::factory()->create(['password' => 'password123']);
    $recipient = User::factory()->create(['password' => 'password123']);
    $cookbook = businessBroadcastingCookbook($inviter);
    $inviterToken = businessBroadcastingToken($inviter);

    Event::fake([CookbookInvitationCreated::class]);
    $this->withToken($inviterToken)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
            'email' => $recipient->email,
            'role' => 'editor',
        ])
        ->assertCreated();

    Event::assertDispatched(CookbookInvitationCreated::class, function (CookbookInvitationCreated $event) use ($recipient): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastOn()[0]->name === 'private-user.'.$recipient->id
            && ! array_key_exists('email', $payload['invitation'])
            && ! array_key_exists('token', $payload['invitation']);
    });

    $invitation = CookbookInvitation::query()->latest('id')->firstOrFail();
    Event::fake([CookbookInvitationAccepted::class]);
    $this->withToken(businessBroadcastingToken($recipient))
        ->postJson('/api/invitations/'.$invitation->id.'/accept')
        ->assertOk();

    Event::assertDispatched(CookbookInvitationAccepted::class, function (CookbookInvitationAccepted $event) use ($inviter, $cookbook): bool {
        return collect($event->broadcastOn())->pluck('name')->all() === [
            'private-user.'.$inviter->id,
            'private-cookbook.'.$cookbook->public_id,
        ];
    });

    $secondRecipient = User::factory()->create(['password' => 'password123']);
    $this->withToken($inviterToken)
        ->postJson('/api/cookbooks/'.$cookbook->public_id.'/invitations', [
            'email' => $secondRecipient->email,
            'role' => 'reader',
        ])
        ->assertCreated();
    $secondInvitation = CookbookInvitation::query()->latest('id')->firstOrFail();

    Event::fake([CookbookInvitationDeclined::class]);
    $this->withToken(businessBroadcastingToken($secondRecipient))
        ->postJson('/api/invitations/'.$secondInvitation->id.'/decline')
        ->assertOk();

    Event::assertDispatched(CookbookInvitationDeclined::class, function (CookbookInvitationDeclined $event) use ($inviter): bool {
        return $event->broadcastOn()[0]->name === 'private-user.'.$inviter->id
            && $event->broadcastWith()['invitation']['status'] === 'declined';
    });
});
