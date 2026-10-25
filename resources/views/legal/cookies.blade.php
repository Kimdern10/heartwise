<x-legal-page :title="__('Cookie Notice')">
    <p>{{ config('app.name') }} uses only the cookies needed to run the site securely. We do not use advertising, analytics or cross-site tracking cookies.</p>

    <h2>Cookies we use</h2>
    <ul>
        <li><strong>Session cookie</strong> — keeps you signed in while you use the site.</li>
        <li><strong>XSRF-TOKEN</strong> — protects forms against cross-site request forgery.</li>
        <li><strong>Remember me</strong> (optional) — keeps you signed in on this device if you tick “Remember me”.</li>
    </ul>
    <p>We also store a small note in your browser’s local storage after you close the cookie banner, so it is not shown again.</p>

    <h2>Your choices</h2>
    <p>You can block or delete cookies in your browser settings, but you will not be able to sign in without the session cookie. If we ever add optional cookies (for example analytics), we will ask first and honor Global Privacy Control signals.</p>
</x-legal-page>
