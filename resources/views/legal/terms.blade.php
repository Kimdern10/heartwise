@php($app = config('app.name'))
@php($email = config('dating.contact_email'))

<x-legal-page :title="__('Terms of Service')">
    <p>These Terms are an agreement between you and {{ config('dating.operator') }} (“{{ $app }}”, “we”, “us”). By creating an account you agree to these Terms, our <a href="{{ route('legal.privacy') }}">Privacy Policy</a> and our <a href="{{ route('legal.guidelines') }}">Community Guidelines</a>.</p>

    <h2>1. Who can use {{ $app }}</h2>
    <ul>
        <li>You must be <strong>at least 18 years old</strong>. We ask for your date of birth at sign-up and remove anyone we believe is under 18.</li>
        <li>You must be legally able to enter this agreement and not be barred from using the service under US law.</li>
        <li>You must not be a registered sex offender, and you must not have been convicted of a violent or sexual crime.</li>
        <li>You may only have one account, and it must be about you — a real person — with accurate information and photos of yourself.</li>
    </ul>

    <h2>2. Your account</h2>
    <p>Keep your password safe and tell us at {{ $email }} if you think someone else has accessed your account. You are responsible for activity on your account.</p>

    <h2>3. Profile review and verification</h2>
    <p>Our moderators review new profiles, profile changes and photos before they are shown to other members. The “verified” badge means a member’s selfie matched their photos at the time of review. It does <strong>not</strong> mean we have checked their identity documents or criminal background. We do not conduct criminal background checks on members.</p>

    <h2>4. How you must behave</h2>
    <p>You agree to follow our <a href="{{ route('legal.guidelines') }}">Community Guidelines</a>. In particular you must not ask other members for money or financial information, harass anyone, post sexual content, use the service for commercial purposes or advertising, or create fake or automated accounts.</p>

    <h2>5. Safety</h2>
    <p>You are responsible for your interactions with other members. Read our <a href="{{ route('legal.safety') }}">safety tips</a>. Never send money or gift cards to someone you have met online. Report anyone who breaks these Terms.</p>

    <h2>6. Moderation, suspension and termination</h2>
    <p>We may remove content, suspend or permanently close accounts that break these Terms or put others at risk. You may delete your account at any time from Settings → Account.</p>

    <h2>7. Paid features</h2>
    <p>Joining, liking and chatting with your matches is free. If we offer paid subscriptions, we will show the price, billing period and renewal terms before you pay. Payments are handled by our payment provider (Stripe). You will be able to cancel any time from your account, and your subscription will then not renew. Required state auto-renewal disclosures will be shown at checkout.</p>

    <h2>8. Your content</h2>
    <p>You keep ownership of the photos and text you post. You give us a limited licence to host and display them to other members so the service can work. We delete them when you delete them or your account.</p>

    <h2>9. Disclaimers and limitation of liability</h2>
    <p>The service is provided “as is”. To the fullest extent allowed by law, we are not liable for the conduct of any member on or off the service, or for indirect or consequential damages.</p>

    <h2>10. Changes and contact</h2>
    <p>We may update these Terms and will tell you about important changes. Questions: {{ $email }}.</p>
</x-legal-page>
