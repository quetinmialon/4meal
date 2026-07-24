<?php

namespace Database\Seeders;

use App\Models\Cookbook;
use App\Models\CookbookInvitation;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $alice = User::create(['name' => 'Alice Martin', 'email' => 'alice.martin@example.com', 'email_verified_at' => now()->subMonths(3), 'password' => $password, 'avatar_path' => 'avatars/alice-martin.jpg', 'last_login_at' => now()->subHours(2), 'remember_token_hash' => Str::random(64)]);
        $bob = User::create(['name' => 'Bob Dupont', 'email' => 'bob.dupont@example.com', 'email_verified_at' => now()->subMonths(2), 'password' => $password, 'avatar_path' => 'avatars/bob-dupont.jpg', 'last_login_at' => now()->subDay(), 'remember_token_hash' => Str::random(64)]);
        $charlie = User::create(['name' => 'Charlie Bernard', 'email' => 'charlie.bernard@example.com', 'email_verified_at' => null, 'password' => $password, 'avatar_path' => 'avatars/charlie-bernard.jpg', 'last_login_at' => null, 'remember_token_hash' => Str::random(64)]);

        $alice->oauthAccounts()->create(['provider' => 'google', 'provider_user_id' => 'google-alice-123456', 'provider_email' => $alice->email, 'access_token' => 'seed-google-access-token', 'refresh_token' => 'seed-google-refresh-token', 'token_expires_at' => now()->addHour()]);

        $family = Cookbook::create(['owner_id' => $alice->id, 'name' => 'Les recettes de la famille Martin', 'slug' => 'recettes-famille-martin', 'description' => 'Les recettes transmises et préparées pour les repas du dimanche.', 'image_path' => 'cookbooks/famille-martin.jpg']);
        $week = Cookbook::create(['owner_id' => $bob->id, 'name' => 'Menus de la semaine', 'slug' => 'menus-de-la-semaine', 'description' => 'Organisation des repas rapides, équilibrés et économiques.', 'image_path' => 'cookbooks/menus-semaine.jpg']);
        $family->members()->attach([$alice->id => ['role' => 'owner', 'joined_at' => now()->subMonths(3)], $bob->id => ['role' => 'editor', 'joined_at' => now()->subMonths(2)], $charlie->id => ['role' => 'reader', 'joined_at' => now()->subMonth()]]);
        $week->members()->attach([$bob->id => ['role' => 'owner', 'joined_at' => now()->subMonths(2)], $alice->id => ['role' => 'editor', 'joined_at' => now()->subMonth()]]);

        $tags = collect([
            ['name' => 'Végétarien', 'slug' => 'vegetarien', 'color' => '#16A34A'], ['name' => 'Rapide', 'slug' => 'rapide', 'color' => '#F59E0B'], ['name' => 'Dessert', 'slug' => 'dessert', 'color' => '#DB2777'], ['name' => 'Repas familial', 'slug' => 'repas-familial', 'color' => '#2563EB'], ['name' => 'Batch cooking', 'slug' => 'batch-cooking', 'color' => '#7C3AED'],
        ])->mapWithKeys(fn (array $data): array => [$data['slug'] => Tag::create([...$data, 'user_id' => $alice->id])]);

        $ratatouille = $this->createRecipe(['user_id' => $alice->id, 'author_id' => $alice->id, 'title' => 'Ratatouille provençale', 'description' => 'Un plat coloré de légumes mijotés, parfumé aux herbes de Provence.', 'prep_time_minutes' => 25, 'cook_time_minutes' => 45, 'rest_time_minutes' => 10, 'servings' => 4, 'image_path' => 'recipes/ratatouille.jpg', 'visibility' => 'public', 'difficulty' => 'easy', 'notes' => 'Encore meilleure réchauffée le lendemain.', 'source' => 'https://example.com/recettes/ratatouille-provence'], [
            ['name' => 'Aubergines', 'quantity' => 2, 'unit' => 'pièce', 'preparation' => 'coupées en dés', 'group_name' => 'Légumes'], ['name' => 'Courgettes', 'quantity' => 3, 'unit' => 'pièce', 'preparation' => 'coupées en dés', 'group_name' => 'Légumes'], ['name' => 'Tomates', 'quantity' => 800, 'unit' => 'g', 'preparation' => 'concassées', 'group_name' => 'Légumes'], ['name' => 'Huile d’olive', 'quantity' => 3, 'unit' => 'c. à soupe', 'preparation' => null, 'group_name' => 'Assaisonnement'], ['name' => 'Piment d’Espelette', 'quantity' => null, 'unit' => null, 'preparation' => 'à votre goût', 'is_optional' => true, 'group_name' => 'Assaisonnement'],
        ], [['instruction' => 'Faire revenir les aubergines dans l’huile d’olive pendant 8 minutes.', 'duration_minutes' => 8], ['instruction' => 'Ajouter les courgettes, les tomates et les herbes, puis mélanger.', 'duration_minutes' => 5], ['instruction' => 'Couvrir et laisser mijoter à feu doux jusqu’à obtenir des légumes fondants.', 'duration_minutes' => 45]]);
        $curry = $this->createRecipe(['cookbook_id' => $family->id, 'author_id' => $bob->id, 'title' => 'Curry de pois chiches et patate douce', 'description' => 'Un curry crémeux et généreux, idéal pour un dîner sans viande.', 'prep_time_minutes' => 20, 'cook_time_minutes' => 35, 'rest_time_minutes' => 5, 'servings' => 6, 'image_path' => 'recipes/curry-pois-chiches.jpg', 'visibility' => 'cookbook', 'difficulty' => 'medium', 'notes' => 'Servir avec du riz basmati et de la coriandre fraîche.', 'source' => 'Carnet de recettes de Bob'], [['name' => 'Pois chiches', 'quantity' => 400, 'unit' => 'g', 'preparation' => 'égouttés', 'group_name' => 'Base'], ['name' => 'Patate douce', 'quantity' => 600, 'unit' => 'g', 'preparation' => 'en cubes', 'group_name' => 'Base'], ['name' => 'Lait de coco', 'quantity' => 400, 'unit' => 'ml', 'preparation' => null, 'group_name' => 'Sauce'], ['name' => 'Curry doux', 'quantity' => 2, 'unit' => 'c. à café', 'preparation' => null, 'group_name' => 'Épices']], [['instruction' => 'Faire revenir l’oignon et les épices dans une grande cocotte.', 'duration_minutes' => 5], ['instruction' => 'Ajouter la patate douce, le lait de coco et un verre d’eau.', 'duration_minutes' => 5], ['instruction' => 'Incorporer les pois chiches et mijoter jusqu’à ce que la patate douce soit tendre.', 'duration_minutes' => 30]]);
        $tiramisu = $this->createRecipe(['cookbook_id' => $week->id, 'author_id' => $bob->id, 'title' => 'Tiramisu au café', 'description' => 'Le dessert italien classique au mascarpone, café et cacao.', 'prep_time_minutes' => 30, 'cook_time_minutes' => 0, 'rest_time_minutes' => 240, 'servings' => 8, 'image_path' => 'recipes/tiramisu.jpg', 'visibility' => 'private', 'difficulty' => 'medium', 'notes' => 'Préparer la veille pour une texture parfaite.', 'source' => 'https://example.com/desserts/tiramisu'], [['name' => 'Mascarpone', 'quantity' => 500, 'unit' => 'g', 'preparation' => null, 'group_name' => 'Crème'], ['name' => 'Œufs', 'quantity' => 4, 'unit' => 'pièce', 'preparation' => 'jaunes et blancs séparés', 'group_name' => 'Crème'], ['name' => 'Biscuits à la cuillère', 'quantity' => 24, 'unit' => 'pièce', 'preparation' => null, 'group_name' => 'Montage'], ['name' => 'Cacao amer', 'quantity' => 2, 'unit' => 'c. à soupe', 'preparation' => null, 'group_name' => 'Montage']], [['instruction' => 'Fouetter les jaunes avec le sucre puis incorporer le mascarpone.', 'duration_minutes' => 10], ['instruction' => 'Monter les blancs en neige et les incorporer délicatement à la crème.', 'duration_minutes' => 10], ['instruction' => 'Tremper les biscuits dans le café, alterner avec la crème et réfrigérer.', 'duration_minutes' => 240]]);

        $ratatouille->tags()->attach([$tags['vegetarien']->id, $tags['repas-familial']->id]);
        $curry->tags()->attach([$tags['vegetarien']->id, $tags['rapide']->id, $tags['batch-cooking']->id]);
        $tiramisu->tags()->attach([$tags['dessert']->id]);
        $family->linkedRecipes()->attach($ratatouille->id);
        $week->linkedRecipes()->attach($curry->id);
        $alice->favoriteRecipes()->attach([$curry->id, $tiramisu->id]);
        $bob->favoriteRecipes()->attach($ratatouille->id);

        CookbookInvitation::create(['cookbook_id' => $family->id, 'invited_by' => $alice->id, 'email' => $charlie->email, 'token_hash' => hash('sha256', 'seed-family-charlie'), 'role' => 'reader', 'expires_at' => now()->addDays(5)]);
        CookbookInvitation::create(['cookbook_id' => $week->id, 'invited_by' => $bob->id, 'email' => $alice->email, 'token_hash' => hash('sha256', 'seed-week-alice'), 'role' => 'editor', 'expires_at' => now()->subDays(10), 'accepted_at' => now()->subDays(12), 'accepted_by' => $alice->id]);
        CookbookInvitation::create(['cookbook_id' => $family->id, 'invited_by' => $alice->id, 'email' => 'declined@example.com', 'token_hash' => hash('sha256', 'seed-family-declined'), 'role' => 'editor', 'expires_at' => now()->addDays(2), 'declined_at' => now()->subDay(), 'declined_by' => $charlie->id]);
    }

    private function createRecipe(array $attributes, array $ingredients, array $steps): Recipe
    {
        $recipe = Recipe::create($attributes);
        foreach ($ingredients as $position => $ingredient) {
            $recipe->ingredients()->create(['position' => $position + 1, ...$ingredient]);
        }
        foreach ($steps as $position => $step) {
            $recipe->steps()->create(['position' => $position + 1, ...$step]);
        }

        return $recipe;
    }
}
