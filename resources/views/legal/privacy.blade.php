@php($app = config('app.name'))
@php($email = config('dating.contact_email'))

<x-legal-page :title="__('Privacy Policy')">
    <p>This policy explains what personal information {{ $app }} collects, why, and the choices you have. We do not sell your personal information and we do not share it for cross-context behavioral advertising.</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> name, email, password (stored encrypted), date of birth.</li>
        <li><strong>Profile:</strong> display name, gender, who you are interested in, city and state, height, relationship goal, bio and photos.</li>
        <li><strong>Verification selfies:</strong> used only to compare with your photos. Seen only by moderators and deleted as soon as the review is done; we keep only the result.</li>
        <li><strong>Activity:</strong> likes and passes, matches, messages, blocks, reports, and when you were last active.</li>
        <li><strong>Technical data:</strong> IP address and browser type, used for security and to prevent abuse.</li>
    </ul>
    <p>Some of this is “sensitive personal information” under California law (for example, information about your sex life or sexual orientation that can be inferred from your profile, and the contents of your messages). We use it only to provide the service.</p>

    <h2>How we use it</h2>
    <ul>
        <li>To run the service: show your profile, find people you may like, and deliver messages.</li>
        <li>To keep members safe: review profiles and photos, screen messages for scam patterns, investigate reports and enforce our Terms.</li>
        <li>To meet legal obligations and respond to lawful requests.</li>
    </ul>

    <h2>Who can see what</h2>
    <ul>
        <li>Other members see your display name, age (never your birth date), city and state, profile details and approved photos. You can hide your profile or last-active time in Settings → Privacy.</li>
        <li>Only your matches can message you. Blocking someone hides you from each other.</li>
        <li>Photo location (EXIF/GPS) data is removed when you upload.</li>
    </ul>

    <h2>Sharing</h2>
    <p>We share personal information only with service providers who help us run {{ $app }} (such as hosting, email delivery and, if you subscribe, our payment provider Stripe), under contracts that limit their use of it, or when required by law or to protect someone’s safety.</p>

    <h2>Retention</h2>
    <p>We keep your information while your account is open. When you delete your account, we delete your profile, photos, likes, matches and messages. We may keep limited records (for example, of reports and bans) for up to 2 years to prevent banned users from returning and to meet legal obligations.</p>

    <h2>Your rights</h2>
    <p>Depending on where you live (including California under the CCPA/CPRA, and states such as Virginia, Colorado, Connecticut, Texas and others), you may have the right to:</p>
    <ul>
        <li>know and get a copy of the personal information we hold (use “Download my data” in Settings → Privacy);</li>
        <li>correct it (edit your profile at any time);</li>
        <li>delete it (Settings → Account → Delete account);</li>
        <li>limit the use of sensitive personal information, and opt out of sale or sharing (we do not sell or share it);</li>
        <li>not be discriminated against for using these rights.</li>
    </ul>
    <p>To make another request or appeal a decision, email {{ $email }}. We will verify your request using your account email.</p>

    <h2>Security</h2>
    <p>Photos and selfies are stored privately and only shown to signed-in members who are allowed to see them. We use encryption in transit (HTTPS) and hash passwords. No system is perfectly secure, so please use a strong, unique password and turn on two-factor authentication.</p>

    <h2>Children</h2>
    <p>{{ $app }} is only for adults 18 and over. We do not knowingly collect information from anyone under 18 and will delete such accounts.</p>

    <h2>Contact</h2>
    <p>{{ config('dating.operator') }} — {{ $email }}</p>
</x-legal-page>
