<?php

namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\CookbookMessage;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Models\RecipeAudit;
use App\Models\RecipeComment;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\SavedSearch;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rich local/demo dataset. The password is intentionally configurable and
 * must only be used in a local/development database, never in production.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD_ENV = 'SUPMEAL_DEMO_PASSWORD';

    /** @var Collection<int, User> */
    private Collection $users;

    /** @var Collection<int, Cookbook> */
    private Collection $cookbooks;

    /** @var Collection<int, Recipe> */
    private Collection $recipes;

    /** @var Collection<int, User> */
    private Collection $activeUsers;

    public function run(): void
    {
        $this->users = $this->createUsers();
        $this->activeUsers = $this->users->reject(fn (User $user): bool => $user->email === 'demo.empty@supmeal.test')->values();
        $this->cookbooks = $this->createCookbooks();
        $tags = $this->createTags();
        $this->recipes = $this->createRecipes($tags);
        $this->createInvitations();
        $this->createFavoritesAndRatings();
        $this->createMealPlans();
        $this->createMessages();
        $this->createComments();
        $this->createSavedSearches();
        $this->createOAuthAccounts();
        $this->createNotificationPreferences();
        $this->createNotifications();
        $this->createRecipeAudits();
    }

    /** @return Collection<int, User> */
    private function createUsers(): Collection
    {
        // SUPMEAL_DEMO_PASSWORD is a local/dev-only secret. Set it in .env;
        // the fallback exists only to make a fresh local demo immediately usable.
        $password = Hash::make((string) env(self::PASSWORD_ENV, 'supmeal-demo-only-change-me'));
        $definitions = [
            ['name' => 'Demo Owner', 'email' => 'demo.owner@supmeal.test'],
            ['name' => 'Demo Editor', 'email' => 'demo.editor@supmeal.test'],
            ['name' => 'Demo Commenter', 'email' => 'demo.commenter@supmeal.test'],
            ['name' => 'Demo Reader', 'email' => 'demo.reader@supmeal.test'],
            ['name' => 'Demo Empty', 'email' => 'demo.empty@supmeal.test'],
        ];
        $users = collect($definitions)->map(fn (array $definition): User => User::factory()->create([
            ...$definition,
            'password' => $password,
            'email_verified_at' => now()->subMonths(4),
            'last_login_at' => $definition['email'] === 'demo.empty@supmeal.test' ? null : now()->subDays(random_int(1, 12)),
        ]));

        return $users->merge(User::factory()->count(15)->create([
            'password' => $password,
            'email_verified_at' => now()->subDays(random_int(10, 120)),
            'last_login_at' => now()->subDays(random_int(1, 90)),
        ]));
    }

    /** @return Collection<int, Cookbook> */
    private function createCookbooks(): Collection
    {
        $owner = $this->user('demo.owner@supmeal.test');
        $editor = $this->user('demo.editor@supmeal.test');
        $commenter = $this->user('demo.commenter@supmeal.test');
        $reader = $this->user('demo.reader@supmeal.test');
        $cookbooks = collect([
            ['name' => 'Le carnet de Demo Owner', 'owner' => $owner, 'description' => 'Le cookbook très actif de démonstration.'],
            ['name' => 'Dîners de la semaine', 'owner' => $owner, 'description' => 'Menus rapides et équilibrés pour les soirs chargés.'],
            ['name' => 'Cuisine familiale', 'owner' => $owner, 'description' => 'Recettes transmises et grands plats du dimanche.'],
            ['name' => 'Atelier de Demo Editor', 'owner' => $editor, 'description' => 'Espace de travail partagé de l’équipe cuisine.'],
        ])->map(fn (array $data): Cookbook => Cookbook::factory()->create([
            'owner_id' => $data['owner']->id,
            'name' => $data['name'],
            'description' => $data['description'],
            'created_at' => now()->subMonths(random_int(2, 8)),
        ]));

        foreach ($cookbooks as $cookbook) {
            $this->member($cookbook, $cookbook->owner, 'owner', 8);
        }
        $this->member($cookbooks[0], $editor, 'editor', 7);
        $this->member($cookbooks[0], $commenter, 'commenter', 5);
        $this->member($cookbooks[0], $reader, 'reader', 4);
        $this->member($cookbooks[1], $editor, 'editor', 6);
        $this->member($cookbooks[1], $commenter, 'commenter', 4);
        $this->member($cookbooks[1], $reader, 'reader', 3);
        $this->member($cookbooks[2], $editor, 'reader', 5);
        $this->member($cookbooks[2], $commenter, 'commenter', 4);
        $this->member($cookbooks[3], $owner, 'editor', 6);
        $this->member($cookbooks[3], $commenter, 'commenter', 3);

        $extra = collect(range(1, 4))->map(function (int $index): Cookbook {
            $owner = $this->activeUsers->random();
            $cookbook = Cookbook::factory()->create(['owner_id' => $owner->id, 'name' => "Cuisine partagée $index"]);
            $this->member($cookbook, $owner, 'owner', 3);
            if ($cookbook->getKey() % 2 === 0) {
                $this->member($cookbook, $this->activeUsers->where('id', '!=', $owner->id)->random(), 'reader', 2);
            }

            return $cookbook;
        });

        return $cookbooks->merge($extra);
    }

    private function member(Cookbook $cookbook, User $user, string $role, int $monthsAgo): void
    {
        $cookbook->members()->syncWithoutDetaching([$user->id => ['role' => $role, 'joined_at' => now()->subMonths($monthsAgo)]]);
    }

    /** @return Collection<int, Tag> */
    private function createTags(): Collection
    {
        $names = ['Végétarien', 'Vegan', 'Rapide', 'Dessert', 'Petit déjeuner', 'Batch cooking', 'Familial', 'Sans gluten', 'Économique', 'Saisonnier', 'Poulet', 'Poisson', 'Pâtes', 'Soupe', 'Salade', 'Brunch', 'Apéritif', 'Confort food', 'Healthy', 'Four', 'Barbecue', 'Asiatique', 'Méditerranéen', 'Express', 'Fêtes'];

        return collect($names)->map(fn (string $name): Tag => Tag::factory()->create(['user_id' => $this->user('demo.owner@supmeal.test')->id, 'name' => $name, 'slug' => Str::slug($name)]));
    }

    /** @param Collection<int, Tag> $tags */
    private function createRecipes(Collection $tags): Collection
    {
        $recipes = collect();
        $counts = [18, 15, 12, 12, 10, 8, 7, 6];
        foreach ($this->cookbooks as $cookbookIndex => $cookbook) {
            for ($index = 0; $index < $counts[$cookbookIndex]; $index++) {
                $author = $cookbook->members->random();
                $recipe = Recipe::factory()->inCookbook($cookbook)->create(['author_id' => $author->id, 'visibility' => $index % 4 === 0 ? 'public' : 'cookbook', 'created_at' => now()->subDays(random_int(5, 210))]);
                $this->addRecipeContent($recipe);
                $recipe->tags()->attach($tags->random(random_int(2, 5))->pluck('id'));
                $recipes->push($recipe);
            }
        }
        for ($index = 0; $index < 20; $index++) {
            $owner = $this->activeUsers->random();
            $recipe = Recipe::factory()->create(['user_id' => $owner->id, 'author_id' => $owner->id, 'visibility' => $index % 3 === 0 ? 'public' : 'private', 'created_at' => now()->subDays(random_int(5, 180))]);
            $this->addRecipeContent($recipe);
            $recipe->tags()->attach($tags->random(random_int(1, 4))->pluck('id'));
            $recipes->push($recipe);
        }

        return $recipes;
    }

    private function addRecipeContent(Recipe $recipe): void
    {
        for ($position = 1; $position <= random_int(4, 7); $position++) {
            RecipeIngredient::factory()->atPosition($position)->create(['recipe_id' => $recipe->id]);
        }
        for ($position = 1; $position <= random_int(3, 6); $position++) {
            RecipeStep::factory()->atPosition($position)->create(['recipe_id' => $recipe->id]);
        }
    }

    private function createInvitations(): void
    {
        $owner = $this->user('demo.owner@supmeal.test');
        foreach (range(1, 5) as $index) {
            CookbookInvitation::factory()->pending()->create(['cookbook_id' => $this->cookbooks[$index % 4]->id, 'invited_by' => $owner->id, 'email' => "pending-$index@external.test"]);
        }
        foreach (range(1, 5) as $index) {
            $invitee = $this->activeUsers->where('id', '!=', $owner->id)->random();
            CookbookInvitation::factory()->accepted($invitee)->create(['cookbook_id' => $this->cookbooks[$index % 4]->id, 'invited_by' => $owner->id, 'email' => $invitee->email]);
        }
        foreach (range(1, 5) as $index) {
            CookbookInvitation::factory()->refused()->create(['cookbook_id' => $this->cookbooks[($index + 2) % 4]->id, 'invited_by' => $owner->id, 'email' => "refused-$index@external.test"]);
        }
    }

    private function createFavoritesAndRatings(): void
    {
        foreach ($this->recipes as $recipe) {
            $eligible = $recipe->cookbook_id === null ? collect([$this->user((string) $recipe->user_id)]) : $recipe->cookbook->members;
            foreach ($eligible->random(min(2, $eligible->count())) as $user) {
                $user->favoriteRecipes()->syncWithoutDetaching([$recipe->id]);
                $recipe->ratings()->firstOrCreate(['user_id' => $user->id], ['rating' => random_int(3, 5)]);
            }
        }
    }

    private function createMealPlans(): void
    {
        foreach ($this->recipes->whereNull('cookbook_id')->take(15) as $recipe) {
            PlannedMeal::factory()->create(['user_id' => $recipe->user_id, 'recipe_id' => $recipe->id, 'date' => now()->addDays(random_int(1, 30))->toDateString()]);
        }
        foreach ($this->recipes->whereNotNull('cookbook_id')->take(25) as $recipe) {
            PlannedMeal::factory()->inCookbook($recipe->cookbook)->create(['recipe_id' => $recipe->id, 'date' => now()->addDays(random_int(1, 30))->toDateString()]);
        }
        foreach ($this->recipes->whereNull('cookbook_id')->take(3) as $recipe) {
            PlannedMeal::factory()->recurring()->create(['user_id' => $recipe->user_id, 'recipe_id' => $recipe->id]);
        }
    }

    private function createMessages(): void
    {
        foreach ($this->cookbooks as $index => $cookbook) {
            $count = $index < 4 ? 24 : ($index < 7 ? 17 : 4);
            for ($messageIndex = 0; $messageIndex < $count; $messageIndex++) {
                CookbookMessage::factory()->create(['cookbook_id' => $cookbook->id, 'user_id' => $cookbook->members->random()->id, 'created_at' => now()->subDays(random_int(1, 180))]);
            }
        }
    }

    private function createComments(): void
    {
        $roots = collect();
        foreach ($this->recipes->whereNotNull('cookbook_id')->take(80) as $recipe) {
            $roots->push(RecipeComment::factory()->create(['recipe_id' => $recipe->id, 'user_id' => $this->commentingMembers($recipe->cookbook)->random()->id, 'created_at' => now()->subDays(random_int(1, 160))]));
        }
        foreach ($roots->take(45) as $root) {
            RecipeComment::factory()->reply($root)->create(['recipe_id' => $root->recipe_id, 'user_id' => $this->commentingMembers($root->recipe->cookbook)->random()->id, 'created_at' => now()->subDays(random_int(1, 90))]);
        }
    }

    /** @return Collection<int, User> */
    private function commentingMembers(Cookbook $cookbook): Collection
    {
        return $cookbook->members->filter(fn (User $user): bool => in_array($user->pivot->role, ['owner', 'editor', 'commenter', 'moderator'], true))->values();
    }

    private function createSavedSearches(): void
    {
        foreach ($this->activeUsers->take(12) as $user) {
            SavedSearch::factory()->create(['user_id' => $user->id]);
            SavedSearch::factory()->vegetarian()->create(['user_id' => $user->id]);
        }
    }

    private function createOAuthAccounts(): void
    {
        $owner = $this->user('demo.owner@supmeal.test');
        $editor = $this->user('demo.editor@supmeal.test');
        foreach ([[$owner, 'google'], [$owner, 'microsoft'], [$editor, 'google']] as [$user, $provider]) {
            $user->oauthAccounts()->create(['provider' => $provider, 'provider_user_id' => "demo-$provider-{$user->id}", 'provider_email' => $user->email, 'access_token' => Str::random(40), 'refresh_token' => Str::random(40), 'token_expires_at' => now()->addDays(30)]);
        }
        foreach ($this->activeUsers->take(5) as $user) {
            $user->oauthAccounts()->create(['provider' => 'google', 'provider_user_id' => "google-user-{$user->id}", 'provider_email' => $user->email, 'access_token' => Str::random(40), 'refresh_token' => null, 'token_expires_at' => now()->addHour()]);
        }
    }

    private function createNotificationPreferences(): void
    {
        foreach ($this->activeUsers->take(10) as $user) {
            foreach (NotificationType::current() as $type) {
                $user->notificationPreferences()->create(['type' => $type, 'channel' => $type === NotificationType::CookbookMessage ? NotificationChannel::Web : NotificationChannel::Both]);
            }
        }
    }

    private function createNotifications(): void
    {
        foreach ($this->activeUsers->take(12) as $index => $user) {
            foreach (range(1, 4) as $notificationIndex) {
                DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\DemoNotification', 'notifiable_type' => User::class, 'notifiable_id' => $user->id, 'data' => json_encode(['type' => NotificationType::current()[$notificationIndex % 4]->value, 'message' => 'Activité récente dans vos recettes.'], JSON_THROW_ON_ERROR), 'read_at' => ($index + $notificationIndex) % 3 === 0 ? now()->subDays($notificationIndex) : null, 'created_at' => now()->subDays($notificationIndex + $index), 'updated_at' => now()->subDays($notificationIndex + $index)]);
            }
        }
    }

    private function createRecipeAudits(): void
    {
        foreach ($this->recipes as $recipe) {
            $actor = $recipe->cookbook_id === null ? $recipe->user : $recipe->cookbook->members->random();
            RecipeAudit::factory()->create(['recipe_id' => $recipe->id, 'actor_id' => $actor->id, 'type' => RecipeAudit::CREATED, 'created_at' => $recipe->created_at]);
            RecipeAudit::factory()->updated()->create(['recipe_id' => $recipe->id, 'actor_id' => $actor->id, 'created_at' => now()->subDays(random_int(1, 60))]);
        }
    }

    private function user(string $identifier): User
    {
        return is_numeric($identifier)
            ? $this->users->firstOrFail(fn (User $user): bool => (string) $user->id === $identifier)
            : $this->users->firstOrFail(fn (User $user): bool => $user->email === $identifier);
    }
}
