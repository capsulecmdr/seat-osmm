@php
    $custom = setting('custom_signin_message', true);

    // Build core message
    if (!empty($custom)) {
        $messageCore = $custom;

        // Strip out any [[profile]] placeholders so they don't inject buttons inside the box
        $messageCore = preg_replace('/\[\[(.*?)\]\]/', '', $messageCore);

    } else {
        $messageCore = trans('web::seat.login_welcome');
    }

    // Build final message with wrapper + single button outside
    $signin_message = sprintf(
        '<div id="signInMessageDiv" style="background-color:#ffffffaa; text-align:center;" class="box w-100 d-none">%s</div>
                 <div class="box-body text-center mt-10">
                    <a id="LoginBtn" href="%s" class="blur-link d-none" disabled style="border-radius:5px;">
                        <img src="%s" alt="LOG IN with EVE Online">
                    </a>
                 </div>',
        $messageCore,
        route('seatcore::auth.eve'),
        asset('web/img/evesso.png')
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

        /* Left-side covenant rail (fixed, non-intrusive) */
        .covenant-rail {
            position: fixed;
            left: 24px;
            top: 12vh;
            width: 320px;
            /* narrow rail */
            z-index: 3000;
            pointer-events: none;
            /* do not intercept clicks */
            user-select: none;
            /* non-selectable text */
            color: #cfd3da;
            /* cold, muted gray */
            opacity: 0.88;
            text-shadow: 0 1px 2px rgba(0, 0, 0, .55);
            /* readability on dark BG */
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background: rgba(10, 14, 18, 0.28);
            backdrop-filter: blur(6px) saturate(110%);
            -webkit-backdrop-filter: blur(6px) saturate(110%);
            opacity: 0.92;
            text-shadow: 0 1px 2px rgba(0, 0, 0, .55);
        }

        .covenant-rail ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .covenant-rail li {
            margin: 0 0 12px 0;
            line-height: 1.25;
            font-size: 14px;
            letter-spacing: .2px;
            /* subtle divider glow */
            border-left: 2px solid rgba(207, 211, 218, .25);
            padding-left: 10px;
        }

        .covenant-rail .typed-dim {
            opacity: .6;
        }

        /* fade older lines slightly */
        .covenant-rail .typed-older {
            opacity: .35;
        }

        /* oldest lines even dimmer */

        /* Make sure nothing in the login card overlaps the rail spacing on very small screens */
        @media (max-width: 768px) {
            .covenant-rail {
                left: 12px;
                width: 260px;
                top: 10vh;
                font-size: 13px;
            }
        }

        .alert[role="alert"] .container {
            display: flex;
            justify-content: center;
            /* center horizontally */
            align-items: center;
            /* center vertically if multi-line */
        }

        .alert[role="alert"] .mr-3 {
            /* neutralize the left-biased wrapper */
            margin-right: 0 !important;
            text-align: center;
            width: 100%;
        }

        .blur-link {
            display: inline-block;
            /* so overflow can crop the blur edge */
            overflow: hidden;
        }

        .blur-link>img {
            filter: blur(6px);
            -webkit-filter: blur(6px);
            /* Safari fallback */
            transition: filter .25s ease;
            will-change: filter;
        }
    </style>
</head>

<body class="h-vh-100 w-vw-100 d-flex flex-column flex-justify-center flex-align-center"
    style="background-image: url('{{ asset('vendor/capsulecmdr/seat-osmm/img/bg_spacestation.png') }}');background-size: 100% auto; background-position: center; background-repeat: no-repeat;">
    

    <!-- Covenant Protocols: Left Typer Rail -->
    <aside class="covenant-rail" aria-hidden="true">
        <ul>
            <li>
                <h4 class="typer" data-text="// Initiating: COVENANT PROTOCOLS //"></h4>
            </li>
            <li><span class="typer"
                    data-text="VEIL OF SILENCE — All transmissions are captured. Disclosure prohibited. to Breach is to be sanctioned."></span>
            </li>
            <li><span class="typer"
                    data-text="IRON WITNESS — All activity is monitored. All actions logged. Observation is permanent."></span>
            </li>
            <li><span class="typer"
                    data-text="CHAIN OF SUBMISSION — Engagement constitutes consent. Consent is binding. Binding is endures."></span>
            </li>
            <li><span class="typer"
                    data-text="ANVIL OF TRUTH — Integrity is absolute. Falsehood is destroyed. Only verified truth remains."></span>
            </li>
            <li><span class="typer"
                    data-text="SHADOW LEDGER — Records are immutable. Violations are indelible. Nothing is forgotten."></span>
            </li>
            <li><span class="typer"
                    data-text="SANCTION ETERNAL — Breach triggers enforcement. Enforcement is automatic. The Forge does not forgive."></span>
            </li>
        </ul>
    </aside>

    <div class="d-flex flex-column flex-align-items-center w-100 mt-10">
        <div class="avatar">
            <img src="https://anvil.capsulecmdr.com/storage/blackanvilsociety.jpg" style="border-radius:50%;">
        </div>
        <div class="mt-10 w-50">
            {!! $signin_message !!}
        </div>
    </div>

    <div class="system-options">
        @if(osmm_setting('osmm_maintenance_enabled') == 1)
            @php
                $reason = osmm_setting('osmm_maintenance_reason');
                $description = osmm_setting('osmm_maintenance_description');
            @endphp
            <button class="outline no-border reduce-2" disabled>{!! $reason . ": " . $description !!}</button>
            <button class="square outline no-border" disabled style="color:#EEE; background-color:#ff000099"><span class="mif-network"></span></button>
        {{-- @endif
        <button class="outline no-border reduce-2">ENG</button>
        <button class="square outline no-border"><span class="mif-network"></span></button>
        <button class="square outline no-border"><span class="mif-keyboard"></span></button>
        <button class="square outline no-border"><span class="mif-power"></span></button> --}}
    </div>
    <script src="{{ asset('vendor/capsulecmdr/seat-osmm/js/metro.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lines = Array.from(document.querySelectorAll('.covenant-rail .typer'));
            const typingSpeed = 40;
            const delayBetween = 500;

            function typeLine(el, text, done, { delayAfter = true } = {}) {
                el.textContent = '';
                let i = 0;
                const timer = setInterval(() => {
                el.textContent += text.charAt(i++);
                if (i >= text.length) {
                    clearInterval(timer);
                    delayAfter ? setTimeout(done, delayBetween) : done();
                }
                }, typingSpeed);
            }

            function runSequence(idx = 0) {
                if (idx >= lines.length) {
                // call the local function, not window.enablebtn
                if (typeof enablebtn === 'function') enablebtn();
                return;
                }
                const el = lines[idx];
                const text = el.dataset.text || '';
                const isLast = idx === lines.length - 1;
                typeLine(el, text, () => runSequence(idx + 1), { delayAfter: !isLast });
            }

            runSequence();

            function enablebtn() {
                document.getElementById('LoginBtn').removeAttribute('disabled');
                document.getElementById('LoginBtn').classList.remove('blur-link');                
                document.getElementById('LoginBtn').classList.remove('d-none');
                document.getElementById('signInMessageDiv').classList.remove('d-none');
            }
            });

    </script>


</body>

</html>