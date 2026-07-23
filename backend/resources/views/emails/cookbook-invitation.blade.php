<p>Vous êtes invité(e) à rejoindre le cookbook « {{ $invitation->cookbook->name }} ».</p>
<p>Rôle proposé : {{ $invitation->role }}.</p>
<p>Acceptez l'invitation ici : {{ url('/api/invitations/'.$token) }}</p>
<p>Cette invitation expire le {{ $invitation->expires_at->toDateTimeString() }}.</p>
