@php($logo = $message->embed(public_path('brand/spider-mark.png')))
<x-mail.layout
    :title="'Your '.$workspaceName.' client portal is ready'"
    :preheader="'You can now follow your projects, documents and messages with '.$workspaceName.'.'"
    :brand="$workspaceName"
    :logo="$logo"
    :app-name="$appName"
    :support-email="$supportEmail">

    <div class="m-in1" style="font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#d6121b;">Client portal</div>
    <h1 class="m-ink m-h1 m-in1" style="margin:8px 0 12px 0;font-size:27px;line-height:34px;font-weight:800;letter-spacing:-0.4px;color:#0f172a;">Welcome, {{ $clientName }}</h1>
    <p class="m-text m-in2" style="margin:0 0 24px 0;font-size:15.5px;line-height:25px;color:#475569;">
        {{ $workspaceName }} has given you access to your own client portal. It is the easiest way to see how your projects are going, without chasing for updates.
    </p>

    <div class="m-in2" style="margin:0 0 26px 0;">
        <x-mail.facts :items="[['Portal', $workspaceName], ['Your sign-in email', $email]]" />
    </div>

    <div class="m-in3" style="margin:0 0 30px 0;">
        <x-mail.button :href="$signInUrl">Open your portal</x-mail.button>
    </div>

    <div class="m-in3">
        <div class="m-ink" style="margin:0 0 14px 0;font-size:13px;font-weight:800;letter-spacing:0.6px;text-transform:uppercase;color:#0f172a;">How to sign in</div>
        <x-mail.steps :items="[
            ['Open the portal', 'Use the button above, or go to '.$signInUrl],
            ['Enter your email', 'Type '.$email.' and request a code.'],
            ['Enter the code we email you', 'There is no password to remember. Each code works once.'],
        ]" />
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:30px;">
        <tr>
            <td class="m-line m-text" style="border-top:1px solid #e2e8f0;padding-top:20px;font-size:14px;line-height:23px;color:#475569;">
                <strong class="m-ink" style="color:#0f172a;">What you can do there</strong><br>
                See the progress and every step of your project &middot; Download shared documents &middot; Send files and requirements &middot; Chat with the team
            </td>
        </tr>
        <tr>
            <td class="m-muted" style="padding-top:20px;font-size:14px;line-height:22px;color:#475569;">
                <strong class="m-ink" style="color:#0f172a;">{{ $senderName }}</strong>
            </td>
        </tr>
    </table>
</x-mail.layout>
