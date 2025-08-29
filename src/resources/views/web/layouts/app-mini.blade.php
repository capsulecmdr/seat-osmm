@php
    // Build the login button once
    $buttonHtml = sprintf(
        '<div class="box-body text-center">
            <a href="%s">
                <img src="%s" alt="LOG IN with EVE Online">
            </a>
        </div>',
        route('seatcore::auth.eve'),
        asset('web/img/evesso.png')
    );

    // Base welcome (escape % for sprintf safety)
    $welcome = str_replace('%', '%%', trans('web::seat.login_welcome'));

    $custom = setting('custom_signin_message', true);
    $messageCore = '';

    if (!empty($custom)) {
        // Start with custom
        $messageCore = $custom;

        // Replace [[profile]] markers with proper login buttons
        $auth_profiles = setting('sso_scopes', true) ?? [];
        $hadMarker = false;

        foreach ($auth_profiles as $profile) {
            $name = $profile->name ?? 'default';
            $pattern = '/\[\[(' . preg_quote($name, '/') . ')\]\]/';

            $messageCore = preg_replace_callback($pattern, function ($m) {
                return sprintf(
                    '<div class="box-body text-center">
                        <a href="%s">
                            <img src="%s" alt="LOG IN with EVE Online">
                        </a>
                    </div>',
                    route('seatcore::auth.eve.profile', $m[1]),
                    asset('web/img/evesso.png')
                );
            }, $messageCore, -1, $count);

            if ($count > 0) $hadMarker = true;
        }

        // If no [[profile]] marker was present, append the default login button
        if (!$hadMarker) {
            $messageCore .= $buttonHtml;
        }
    } else {
        // Default: welcome + default login button
        $messageCore = $welcome . $buttonHtml;
    }

    // NOW wrap the final message so your outer div always appears
    $signin_message = sprintf(
        '<div style="background-color:#fff; text-align:center;" class="box w-100">%s</div>',
        $messageCore
    );
@endphp

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('web::includes.favicon')
    <title>SeAT | @yield('title', 'Eve Online API Tool')</title>
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/metro.css">
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/icons.css">
    <style>
        #page-content {
            display: flex;
            justify-content: center;
            align-items: center;
            background: url("/images/bg-light.avif") center no-repeat;
            background-size: cover;
        }

        .dark-side {
            #page-content {
                background: url("/images/bg-dark.avif") center no-repeat;
                background-size: cover;
            }
        }

        .avatar {
            width: 140px;
            height: 140px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);

            span {
                font-size: 96px;
            }
        }

        .dark-side {
            .avatar {
                background: #6d7278;
            }
        }

        .other-user {
            position: absolute;
            bottom: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            flex-direction: column;

            .avatar {
                width: 36px;
                height: 36px;

                span {
                    font-size: 24px;
                }
            }
        }

        .system-options {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 0;
            flex-direction: row;
            flex-wrap: nowrap;
        }
    </style>
</head>

<body class="h-vh-100 w-vw-100 d-flex flex-column flex-justify-center flex-align-center" style="background-image: url('{{ asset('vendor/capsulecmdr/seat-osmm/img/bg_spacestation.png') }}');">
    @if(osmm_setting('osmm_maintenance_enabled') == 1)
      @php
        $reason = osmm_setting('osmm_maintenance_reason');
        $description = osmm_setting('osmm_maintenance_description');
      @endphp
      <div class="alert alert-danger mb-0 rounded-0" role="alert" style="position:sticky; top:0; z-index: 1050;">
        <div class="container d-flex justify-content-between align-items-center">
          <div class="mr-3">
            <strong>{!! $reason !!}</strong>
            <span class="ml-2">{!! $description !!}</span>
          </div>
        </div>
      </div>
    @endif
    @include('seat-osmm::includes.announcement-banner')

    <div class="d-flex flex-column flex-align-items-center w-100">
        <div class="avatar">
            <img src="https://anvil.capsulecmdr.com/storage/blackanvilsocietyicon2.png">
        </div>
        <div class="mt-10 w-50">
            {!! $signin_message !!}
        </div>
    </div>

    <div class="system-options">
        <button class="outline no-border reduce-2">ENG</button>
        <button class="square outline no-border"><span class="mif-network"></span></button>
        <button class="square outline no-border"><span class="mif-keyboard"></span></button>
        <button class="square outline no-border"><span class="mif-power"></span></button>
    </div>
    <script src="{{ asset('vendor/capsulecmdr/seat-osmm/js/metro.js') }}" defer></script>
    <script>
        
    </script>
</body>

</html>