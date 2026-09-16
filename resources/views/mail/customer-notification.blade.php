{{--
    Plain, table-based HTML with inline styles: email clients support little
    else, and `dir` on the wrapper is what makes Arabic read correctly.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->subject() }}</title>
</head>
<body style="margin:0; padding:0; background-color:#fafaf9; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1c1917;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fafaf9; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px; background-color:#ffffff; border:1px solid #e7e5e4; border-radius:12px;">
                    <tr>
                        <td style="padding:24px 28px 8px 28px;">
                            <p style="margin:0; font-size:18px; font-weight:600; color:#115e59;">{{ $booking->tenant->name }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 0 28px;">
                            <h1 style="margin:0; font-size:20px; line-height:1.4; font-weight:600;">{{ $notification->heading() }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#fafaf9; border-radius:8px;">
                                @foreach ($notification->lines() as $line)
                                    <tr>
                                        <td style="padding:{{ $loop->first ? '14px' : '4px' }} 16px {{ $loop->last ? '14px' : '4px' }} 16px; font-size:14px; line-height:1.6;">{{ $line }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if ($notification->actionUrl())
                        <tr>
                            <td style="padding:24px 28px 0 28px;">
                                <a href="{{ $notification->actionUrl() }}"
                                   style="display:inline-block; background-color:#0f766e; color:#ffffff; text-decoration:none; font-size:14px; font-weight:600; padding:12px 20px; border-radius:8px;">
                                    {{ $notification->actionLabel() }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:24px 28px 28px 28px;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#78716c;">
                                {{ __('All times are shown in :timezone.', ['timezone' => $booking->tenant->timezone]) }}
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 0 0; font-size:11px; color:#a8a29e;">
                    {{ __('Sent by :app on behalf of :business.', ['app' => config('app.name'), 'business' => $booking->tenant->name]) }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
