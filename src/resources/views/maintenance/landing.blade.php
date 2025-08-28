@php
    $enabled = (int) (osmm_setting('osmm_maintenance_enabled', '0'));
    $target = \Route::has('osmm.home')
        ? route('osmm.home')
        : (\Route::has('seatcore::home') ? route('seatcore::home') : url('/'));
@endphp

@if ($enabled !== 1)
    <script>window.location.replace(@json($target));</script>
    <noscript>
        <meta http-equiv="refresh" content="0;url={{ $target }}">
    </noscript>
@endif

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance</title>
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/metro.css">
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/icons.css">
    <style>
        .navview-content {
            overflow: hidden !important;
        }

        .cog {
            position: absolute;
            z-index: -1;
            color: #fefefe;
        }

        #cog1 {
            top: 20px;
            left: 20px;
            font-size: 244px;
        }

        #cog2 {
            top: 180px;
            left: 180px;
            font-size: 244px;

        }

        #cog3,
        #cog4 {
            top: 20px;
            right: 80px;
            font-size: 144px;
        }

        #cog4 {
            top: auto;
            bottom: 20px;
            left: 100px;
            right: auto;
        }

        #cog5 {
            font-size: 80px;
            right: 120px;
            bottom: 200px;
        }

        #cog1,
        #cog2 {
            span {
                animation-duration: 5s !important;
            }
        }

        #cog3 {
            span {
                animation-duration: 7s !important;
            }
        }

        #cog4 {
            span {
                animation-duration: 15s !important;
            }
        }

        #cog4 {
            span {
                animation-duration: 10s !important;
            }
        }

        .dark-side {
            .cog {
                color: #212225 !important;
            }
        }
    </style>
</head>

<body class="h-vh-100 w-vw-100 d-flex flex-column flex-justify-center flex-align-center"
    style="background-color:#f8f8f8;overflow:hidden;">
    <div id="root" class="h-100 w-100 d-flex flex-center flex-column">
        <div class="h-100 w-100 d-flex flex-column flex-center no-overflow">
            <div class="display4">
                <span class="mif-tools"></span>
            </div>
            <div class="row flex-justify-content-center">
                <h2 class="text-center">
                    @if(!empty($reason))
                        {{ $reason }}
                    @endif
                </h2>
                <div class="text-leader2 text-center cell-md-6">
                    <p>
                        @if(!empty($desc))
                            {{ $desc }}
                        @endif
                    </p>
                    <p class="text-muted">
                        Checking for maintenance update in:
                    </p>
                </div>
                <div id="id-object-1" data-role="countdown" data-days="0" data-hours="0" data-minutes="5"
                    data-seconds="0" data-font-size="48" data-animate="slide" data-duration="2000">
                </div>
                <div class="w-75 mt-10">
                    <div class="row">
                        <div class="cell-ld-4">
                            
                        </div>
                        <div class="cell-ld-4">
                            
                        </div>
                        <div class="cell-ld-4">
                            
                        </div>
                    </div>
                </div>

                <div class="cog" id="cog1">
                    <span class="mif-cog ani-spin d-block"></span>
                </div>
                <div class="cog" id="cog2">
                    <span class="mif-cog ani-spin-reverse d-block"></span>
                </div>
                <div class="cog" id="cog3">
                    <span class="mif-cog ani-spin d-block"></span>
                </div>
                <div class="cog" id="cog4">
                    <span class="mif-cog ani-spin-reverse d-block"></span>
                </div>
                <div class="cog" id="cog5">
                    <span class="mif-cog ani-spin-reverse d-block"></span>
                </div>
            </div>
        </div>
        <script src="{{ asset('vendor/capsulecmdr/seat-osmm/js/metro.js') }}" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    const cd = Metro.getPlugin('#id-object-1', 'countdown');
                    if (cd) cd.resetWith({ days: 0, hours: 0, minutes: 5, seconds: 0 });
                } catch (e) { console.warn(e); }
            });
        </script>
        <script>setInterval(() => window.location.reload(), 300_000);</script>
</body>

</html>