<h1>{{ $isReply ? 'Nouvelle réponse à votre commentaire' : 'Nouveau commentaire sur votre recette' }}</h1>
<p>Bonjour {{ $user->name }},</p>
<p><strong>{{ $sender->name }}</strong> a publié un commentaire sur « {{ $recipe->title }} ».</p>
<blockquote>{{ $comment->content }}</blockquote>
<p>Connectez-vous à 4meal pour consulter la conversation.</p>
