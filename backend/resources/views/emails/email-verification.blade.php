<p>Vérifiez votre adresse email pour activer votre compte 4meal.</p>
<p>
    <a href="{{ $verificationUrl }}"
       style="display:inline-block;padding:12px 20px;border-radius:999px;background:#2f4520;color:#fffdf9;text-decoration:none;font-weight:700;">
        Vérifier mon adresse email
    </a>
</p>
<p>Ce lien expire dans {{ $expiresInMinutes }} minutes et ne peut être utilisé qu'une seule fois.</p>
<p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>{{ $verificationUrl }}</p>
